<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\Cutting;
use ME\MerchandisingSfl\Models\Production\Entry;

/**
 * The production route of a PO and its piece balances.
 *
 *   cutting → [embroidery] → sewing → [washing] → finishing → final QC → packing
 *
 * Embroidery and washing apply only when the PO says so. Each stage takes in
 * what the previous stage passed (cutting "passes" every piece it cuts):
 *   can take in = previous stage's pass − this stage's input
 *   WIP         = input − pass − reject   (rework stays in WIP until it passes)
 *
 * Reject & Rework screen (entry kind, any stage incl. cutting):
 *   qc     — reject / rework found among pieces the stage already passed:
 *            they leave pass (reject leaves the flow, rework goes back to WIP)
 *   rework — rework fixed: pass / reject out of WIP (like a production entry with no input)
 */
class ProductionFlow
{
    /** Entry stages (cutting has its own screen). key => [label, icon, uses a sewing line] */
    public const STAGES = [
        'embroidery' => ['Embroidery', 'fa-solid fa-spa', false],
        'sewing' => ['Sewing', 'fa-solid fa-shirt', true],
        'washing' => ['Washing', 'fa-solid fa-soap', false],
        'finishing' => ['Finishing', 'fa-solid fa-wand-magic-sparkles', false],
        'final_qc' => ['Final QC', 'fa-solid fa-clipboard-check', false],
        'packing' => ['Packing', 'fa-solid fa-box', false],
    ];

    /** Entry kinds: production (stage screens) and the two of the Reject & Rework screen. */
    public const KINDS = [
        'production' => 'Production',
        'qc' => 'Reject / Rework found',
        'rework' => 'Rework fixed',
    ];

    public static function label(string $stage): string
    {
        return $stage === 'cutting' ? 'Cutting' : (self::STAGES[$stage][0] ?? ucfirst($stage));
    }

    public static function usesLine(string $stage): bool
    {
        return (bool) (self::STAGES[$stage][2] ?? false);
    }

    /** @return list<string> the PO's stages in order, cutting first */
    public function route(OrderPo $po): array
    {
        return array_values(array_filter([
            'cutting',
            $po->needs_embroidery ? 'embroidery' : null,
            'sewing',
            $po->needs_washing ? 'washing' : null,
            'finishing', 'final_qc', 'packing',
        ]));
    }

    public function previous(OrderPo $po, string $stage): ?string
    {
        $route = $this->route($po);
        $i = array_search($stage, $route, true);

        return $i ? $route[$i - 1] : null;
    }

    /**
     * Totals per stage on the PO's route:
     * stage => [input, pass, rework, reject, wip, available].
     * $ignoreEntryId leaves one entry out (used when re-checking an edit).
     *
     * @return array<string, array<string, int>>
     */
    public function summary(OrderPo $po, ?int $ignoreEntryId = null): array
    {
        $cut = (int) Cutting::query()->where('order_po_id', $po->id)->sum('total_qty');

        // P = pass of production + rework-fixed entries; qcOut = pieces taken back out of pass by "found" entries.
        $sums = Entry::query()->where('order_po_id', $po->id)
            ->when($ignoreEntryId, fn ($q) => $q->whereKeyNot($ignoreEntryId))
            ->selectRaw("stage, SUM(input_qty) i,
                SUM(CASE WHEN kind = 'qc' THEN 0 ELSE pass_qty END) p,
                SUM(CASE WHEN kind = 'qc' THEN reject_qty + rework_qty ELSE 0 END) qo,
                SUM(CASE WHEN kind = 'qc' THEN rework_qty ELSE 0 END) qrw,
                SUM(CASE WHEN kind = 'qc' THEN 0 ELSE reject_qty END) prj,
                SUM(rework_qty) rw, SUM(reject_qty) rj")
            ->groupBy('stage')->get()->keyBy('stage');

        $out = [];
        $supplied = 0;
        foreach ($this->route($po) as $stage) {
            $s = $sums->get($stage);
            // Cutting has no production entries: everything cut counts as passed.
            $input = $stage === 'cutting' ? $cut : (int) ($s->i ?? 0);
            $passed = (int) ($s->p ?? 0) + ($stage === 'cutting' ? $cut : 0);
            $out[$stage] = [
                'input' => $input,
                'pass' => max(0, $passed - (int) ($s->qo ?? 0)),
                'rework' => (int) ($s->rw ?? 0),
                'reject' => (int) ($s->rj ?? 0),
                'wip' => max(0, $input - $passed - (int) ($s->prj ?? 0) + (int) ($s->qrw ?? 0)),
                'available' => $stage === 'cutting' ? 0 : max(0, $supplied - $input),
            ];
            $supplied = $out[$stage]['pass'];
        }

        return $out;
    }

    /**
     * Errors (field => message) for an entry of $qty at $stage — input can't
     * exceed what the previous stage passed, pass + reject can't exceed what
     * is in the stage.
     *
     * @param array{input_qty:int, pass_qty:int, rework_qty:int, reject_qty:int} $qty
     */
    public function validate(OrderPo $po, string $stage, array $qty, ?int $ignoreEntryId = null): array
    {
        if (! in_array($stage, $this->route($po), true)) {
            $what = $stage === 'embroidery' ? 'embroidery' : 'washing';

            return ['order_po_id' => "This PO has no {$what} — tick it on the order PO if it needs one."];
        }

        $row = $this->summary($po, $ignoreEntryId)[$stage];
        $prev = self::label($this->previous($po, $stage));
        $errors = [];

        if ($qty['input_qty'] > $row['available']) {
            $errors['input_qty'] = "Only {$row['available']} pcs can come in — {$prev} has passed " . ($row['available'] + $row['input']) . ", {$row['input']} already taken in.";
        }

        $inStage = $row['wip'] + min($qty['input_qty'], $row['available']);
        if ($qty['pass_qty'] + $qty['reject_qty'] > $inStage) {
            $errors['pass_qty'] = "Pass + reject is " . ($qty['pass_qty'] + $qty['reject_qty']) . " pcs but only {$inStage} are in " . self::label($stage) . '.';
        }

        if ($qty['input_qty'] + $qty['pass_qty'] + $qty['rework_qty'] + $qty['reject_qty'] === 0) {
            $errors['input_qty'] = 'Enter at least one quantity.';
        }

        return $errors;
    }

    /** The stage after $stage on the PO's route (null for the last one). */
    public function next(OrderPo $po, string $stage): ?string
    {
        $route = $this->route($po);

        return $route[array_search($stage, $route, true) + 1] ?? null;
    }

    /** Passed pieces still at $stage — the next stage hasn't taken them in yet. */
    public function ready(OrderPo $po, string $stage, ?array $summary = null): int
    {
        $summary ??= $this->summary($po);
        $next = $this->next($po, $stage);

        return max(0, $summary[$stage]['pass'] - ($next ? $summary[$next]['input'] : 0));
    }

    /** Why an entry can't be deleted (its passed pieces already moved on), or null. */
    public function deleteBlocked(Entry $entry): ?string
    {
        $po = $entry->orderPo;
        $after = $this->summary($po, $entry->id);
        $row = $after[$entry->stage];
        // Later entries of this stage passed / rejected pieces this one brought in (e.g. rework fixed after a "found").
        if ($row['pass'] + $row['reject'] + $row['wip'] > $row['input']) {
            return 'Later ' . self::label($entry->stage) . ' entries (e.g. rework fixed) depend on this one — delete them first.';
        }
        $next = $this->next($po, $entry->stage);
        if ($next && $after[$next]['input'] > $row['pass']) {
            return self::label($next) . ' has already taken in these pieces — delete its entries first.';
        }

        return null;
    }

    /**
     * Status rows for many POs (Production Status page): po_id => summary.
     *
     * @param Collection<int, OrderPo> $pos
     * @return array<int, array<string, array<string, int>>>
     */
    public function summaries(Collection $pos): array
    {
        return $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => $this->summary($po)])->all();
    }

    /**
     * Reject / rework counts must be explained: defect rows of that type add
     * up to the count. Part and machine are optional (machine = for machine-wise rejection).
     */
    public function defectErrors($defects, array $qty): array
    {
        $errors = [];
        foreach (['reject' => 'reject_qty', 'rework' => 'rework_qty'] as $type => $field) {
            $rows = $defects->where('type', $type);
            $sum = (int) $rows->sum(fn ($d) => (int) $d['qty']);
            if ($qty[$field] > 0 && $sum !== $qty[$field]) {
                $errors['defects'] = ucfirst($type) . " is {$qty[$field]} pcs but the {$type} rows add up to {$sum} — break it down below.";
            } elseif ($qty[$field] === 0 && $sum > 0) {
                $errors['defects'] = "There are {$type} rows but the {$type} quantity is 0.";
            }
        }
        return $errors;
    }

    /** Active Inventory machines, with the line text they are on (to filter by the chosen line). */
    public static function machines()
    {
        if (! class_exists(\ME\SflInventory\Models\InvMachine::class)) {
            return collect();
        }

        return DB::table('inv_machines')->whereNull('deleted_at')->where('is_active', true)
            ->orderBy('code')->get(['id', 'code', 'name', 'type', 'line']);
    }
}

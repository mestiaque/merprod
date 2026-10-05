<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Collection;
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

        $sums = Entry::query()->where('order_po_id', $po->id)
            ->when($ignoreEntryId, fn ($q) => $q->whereKeyNot($ignoreEntryId))
            ->selectRaw('stage, SUM(input_qty) i, SUM(pass_qty) p, SUM(rework_qty) rw, SUM(reject_qty) rj')
            ->groupBy('stage')->get()->keyBy('stage');

        $out = [];
        $supplied = 0;
        foreach ($this->route($po) as $stage) {
            if ($stage === 'cutting') {
                $row = ['input' => $cut, 'pass' => $cut, 'rework' => 0, 'reject' => 0, 'wip' => 0, 'available' => 0];
            } else {
                $s = $sums->get($stage);
                $input = (int) ($s->i ?? 0);
                $pass = (int) ($s->p ?? 0);
                $reject = (int) ($s->rj ?? 0);
                $row = [
                    'input' => $input, 'pass' => $pass, 'rework' => (int) ($s->rw ?? 0), 'reject' => $reject,
                    'wip' => max(0, $input - $pass - $reject),
                    'available' => max(0, $supplied - $input),
                ];
            }
            $out[$stage] = $row;
            $supplied = $row['pass'];
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
}

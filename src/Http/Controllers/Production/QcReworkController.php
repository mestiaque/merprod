<?php

namespace ME\MerchandisingSfl\Http\Controllers\Production;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Controllers\Controller;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\Entry;
use ME\MerchandisingSfl\Models\Size;
use ME\MerchandisingSfl\Services\ProductionFlow;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Production → QC and Production → Rework (two sidebar items, one controller; the
 * route's `kind` default says which). The menu opens a card per step; a card opens
 * that step's entry form.
 *   QC (kind qc)          — step-wise QC (Buyer QC is its own stage): reject / rework among pieces the step passed
 *   Rework (kind rework)  — rework pieces fixed: pass / reject
 * Cutting and embroidery are part-wise: the entry names the part.
 * Saved as production entries of that stage, so status, reports and T&A count them.
 */
class QcReworkController extends Controller
{
    public function __construct(private readonly ProductionFlow $flow)
    {
    }

    /** Step cards (today's figures) + the entries so far. */
    public function index(Request $request): View
    {
        $this->authorize('msfl_prod_entry.list');
        $kind = $this->kind($request);

        $today = Entry::query()->where('kind', $kind)->whereDate('entry_date', today())
            ->selectRaw('stage, SUM(pass_qty) p, SUM(rework_qty) rw, SUM(reject_qty) rj, COUNT(*) n')->groupBy('stage')->get()->keyBy('stage');

        $entries = Entry::query()->where('kind', $kind)
            ->with(['orderPo.order.buyer', 'orderPo.style', 'orderPo.color', 'size', 'line.floorLine', 'defects', 'creator'])
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->stage))
            ->when($request->filled('order_po_id'), fn ($q) => $q->where('order_po_id', $request->order_po_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->to))
            ->latest('entry_date')->latest('id')->paginate(25)->withQueryString();

        return view('merchandising-sfl::admin.production.qc-rework.index', [
            'kind' => $kind,
            'today' => $today,
            'entries' => $entries,
            'pos' => Lookups::productionPos(),
            'stages' => self::stages($kind),
        ]);
    }

    /** Entry form of one step (picked from its card). */
    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('msfl_prod_entry.add');
        $kind = $this->kind($request);
        $stage = (string) $request->query('stage', old('stage'));
        if (! array_key_exists($stage, self::stages($kind))) {
            return redirect()->route("msfl.production.{$kind}.index");
        }

        $pos = Lookups::productionPos()->filter(fn (OrderPo $po) => in_array($stage, $this->flow->route($po), true))->values();
        $partWise = ProductionFlow::partWise($stage);
        // po => [ready (passed, still here), wip (waiting, incl. rework)] — per part for part-wise steps.
        $balances = $pos->mapWithKeys(function (OrderPo $po) use ($stage, $partWise) {
            $summary = $this->flow->summary($po);
            $b = ['ready' => $this->flow->ready($po, $stage, $summary), 'wip' => $summary[$stage]['wip']];
            if ($partWise) {
                $b['parts'] = collect(ProductionFlow::poParts([$po->id])[$po->id] ?? [])
                    ->merge(array_keys($summary[$stage]['parts'] ?? []))->unique()
                    ->mapWithKeys(fn ($part) => [$part => [
                        'ready' => $this->flow->ready($po, $stage, $summary, $part),
                        'wip' => $stage === 'cutting' ? $summary[$stage]['wip'] : $this->flow->partRow($summary, $stage, $part)['wip'],
                    ]])->all();
            }

            return [$po->id => $b];
        });

        return view('merchandising-sfl::admin.production.qc-rework.create', [
            'kind' => $kind,
            'stage' => $stage,
            'partWise' => $partWise,
            'pos' => $pos,
            'balances' => $balances,
            'sizes' => Size::query()->orderBy('sort_order')->get(['id', 'name']),
            'lines' => ProductionFlow::usesLine($stage) ? Lookups::lines() : collect(),
            'machines' => ProductionFlow::machines(),
            'garmentParts' => Lookups::garmentParts(),
            'selectedPo' => $request->integer('order_po_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.add');
        $kind = $this->kind($request);
        $stage = (string) $request->input('stage');

        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys(self::stages($kind)))],
            'order_po_id' => ['required', Rule::exists('msfl_order_pos', 'id')],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'size_id' => ['nullable', Rule::exists('inv_sizes', 'id')],
            'part_name' => [ProductionFlow::partWise($stage) ? 'required' : 'nullable', 'string', 'max:100', Rule::exists('msfl_garment_parts', 'name')->whereNull('deleted_at')],
            'line_id' => [Rule::requiredIf(ProductionFlow::usesLine($stage)), 'nullable', Rule::exists('msfl_lines', 'id')->whereNull('deleted_at')],
            'pass_qty' => ['nullable', 'integer', 'min:0'],
            'rework_qty' => ['nullable', 'integer', 'min:0'],
            'reject_qty' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'defects' => ['nullable', 'array'],
            'defects.*.type' => ['nullable', 'in:reject,rework'],
            'defects.*.part_name' => ['nullable', 'string', 'max:100', Rule::exists('msfl_garment_parts', 'name')->whereNull('deleted_at')],
            'defects.*.machine_id' => ['nullable', 'integer', Rule::exists('inv_machines', 'id')],
            'defects.*.defect' => ['nullable', 'string', 'max:150'],
            'defects.*.qty' => ['nullable', 'integer', 'min:0'],
        ]);

        $qc = $kind === 'qc';
        $part = ProductionFlow::partWise($stage) ? trim($data['part_name']) : null;
        $usesLine = ProductionFlow::usesLine($stage);
        // QC: reject + rework out of passed pieces. Rework: pass + reject out of the pieces waiting.
        $qty = [
            'input_qty' => 0,
            'pass_qty' => $qc ? 0 : (int) ($data['pass_qty'] ?? 0),
            'rework_qty' => $qc ? (int) ($data['rework_qty'] ?? 0) : 0,
            'reject_qty' => (int) ($data['reject_qty'] ?? 0),
        ];
        $defects = collect($data['defects'] ?? [])
            ->filter(fn ($d) => (int) ($d['qty'] ?? 0) > 0 && in_array($d['type'] ?? null, $qc ? ['reject', 'rework'] : ['reject'], true))->values();

        $po = OrderPo::with('order')->findOrFail($data['order_po_id']);

        $entry = DB::transaction(function () use ($po, $stage, $qc, $part, $qty, $defects, $data, $usesLine) {
            OrderPo::query()->whereKey($po->id)->lockForUpdate()->first();

            $errors = $this->balanceErrors($po, $stage, $qc, $part, $qty) + $this->flow->defectErrors($defects, $qty);
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $entry = Entry::create($qty + [
                'stage' => $stage,
                'kind' => $qc ? 'qc' : 'rework',
                'entry_date' => $data['entry_date'],
                'order_po_id' => $po->id,
                'size_id' => $data['size_id'] ?? null,
                'part_name' => $part,
                'line_id' => $usesLine ? $data['line_id'] : null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);
            foreach ($defects as $d) {
                $entry->defects()->create([
                    'type' => $d['type'],
                    'part_name' => filled($d['part_name'] ?? null) ? trim($d['part_name']) : $part,
                    'machine_id' => $d['machine_id'] ?? null,
                    'defect' => $d['defect'] ?? null,
                    'qty' => (int) $d['qty'],
                ]);
            }

            return $entry;
        });

        $what = $qc ? "QC — reject {$entry->reject_qty}, rework {$entry->rework_qty}" : "rework — pass {$entry->pass_qty}, reject {$entry->reject_qty}";

        return redirect()->route("msfl.production.{$kind}." . ($request->boolean('add_another') ? 'create' : 'index'),
            $request->boolean('add_another') ? ['order_po_id' => $po->id, 'stage' => $stage] : [])
            ->with('success', ProductionFlow::label($stage) . ($part ? " ({$part})" : '') . " {$what} saved.");
    }

    public function destroy(Entry $entry): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.delete');
        abort_unless($entry->kind === request()->route('kind'), 404);

        if ($error = $this->flow->deleteBlocked($entry)) {
            return back()->with('error', $error);
        }

        $entry->delete();

        return back()->with('success', ProductionFlow::label($entry->stage) . ' ' . ProductionFlow::KINDS[$entry->kind] . ' entry deleted.');
    }

    /**
     * Steps per screen: QC is step-wise (Buyer QC is its own stage; nothing to check after packing),
     * Rework covers every step that can hold rework, Buyer QC included.
     */
    public static function stages(string $kind): array
    {
        $all = ['cutting' => 'Cutting'] + collect(ProductionFlow::STAGES)->map(fn ($s) => $s[0])->all();

        return collect($all)->except($kind === 'qc' ? ['final_qc', 'packing'] : ['packing'])->all();
    }

    private function kind(Request $request): string
    {
        return $request->route('kind') === 'rework' ? 'rework' : 'qc';
    }

    private function balanceErrors(OrderPo $po, string $stage, bool $qc, ?string $part, array $qty): array
    {
        if (($po->order->status ?? null) !== 'confirmed') {
            return ['order_po_id' => 'Only a confirmed order can go into production.'];
        }
        if (! in_array($stage, $this->flow->route($po), true)) {
            return ['order_po_id' => 'This PO has no ' . strtolower(ProductionFlow::label($stage)) . ' on its route.'];
        }

        $summary = $this->flow->summary($po);
        $label = ProductionFlow::label($stage) . ($part ? " ({$part})" : '');

        if ($qc) {
            $ready = $this->flow->ready($po, $stage, $summary, $part);
            if ($qty['reject_qty'] + $qty['rework_qty'] === 0) {
                return ['reject_qty' => 'Enter the reject or rework quantity.'];
            }
            if ($qty['reject_qty'] + $qty['rework_qty'] > $ready) {
                return ['reject_qty' => 'Reject + rework is ' . ($qty['reject_qty'] + $qty['rework_qty']) . " pcs but only {$ready} passed pcs are still in {$label} (the rest moved on)."];
            }

            return [];
        }

        $wip = in_array($stage, ProductionFlow::PART_STAGES, true) ? $this->flow->partRow($summary, $stage, (string) $part)['wip'] : $summary[$stage]['wip'];
        if ($qty['pass_qty'] + $qty['reject_qty'] === 0) {
            return ['pass_qty' => 'Enter how many reworked pcs passed or were rejected.'];
        }
        if ($qty['pass_qty'] + $qty['reject_qty'] > $wip) {
            return ['pass_qty' => 'Pass + reject is ' . ($qty['pass_qty'] + $qty['reject_qty']) . " pcs but only {$wip} pcs are waiting in {$label}."];
        }

        return [];
    }
}

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
 *   QC (kind qc)          — step-wise QC (Buyer QC is its own stage): pieces the step passed are
 *                           checked — pass / reject. No rework here.
 *   Rework (kind rework)  — rework found among the step's passed pieces (back to WIP), and / or
 *                           rework fixed: pass / reject
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

        $pos = Lookups::productionPos()->load('sizes')->filter(fn (OrderPo $po) => in_array($stage, $this->flow->route($po), true))->values();
        $partWise = ProductionFlow::partWise($stage);
        // po => size => [ready (passed, still here), wip (waiting, incl. rework)] — and per part for part-wise steps.
        $balances = $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => $po->sizes->mapWithKeys(function ($ps) use ($po, $stage, $partWise) {
            $summary = $this->flow->summary($po, null, $ps->size_id);
            $b = $this->balance($po, $stage, $summary, null);
            if ($partWise) {
                $b['parts'] = collect(ProductionFlow::poParts([$po->id])[$po->id] ?? [])->merge(array_keys($summary[$stage]['parts'] ?? []))->unique()
                    ->mapWithKeys(fn ($part) => [$part => $this->balance($po, $stage, $summary, $part)])->all();
            }

            return [$ps->size_id => $b];
        })->all()]);

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
            'poSizes' => $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => $po->sizes->pluck('size_id')->all()]),
        ]);
    }

    /** ready / wip of a step (one size, optionally one part) for the form's balance box. */
    private function balance(OrderPo $po, string $stage, array $summary, ?string $part): array
    {
        $partRow = $part !== null && in_array($stage, ProductionFlow::PART_STAGES, true);

        return [
            'ready' => $this->flow->ready($po, $stage, $summary, $part),
            'wip' => $partRow ? $this->flow->partRow($summary, $stage, $part)['wip'] : $summary[$stage]['wip'],
        ];
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
            'size_id' => ['required', Rule::exists('inv_sizes', 'id')],
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
        // QC: passed pieces checked — pass (counted only) / reject. Rework: found (passed → WIP), fixed pass / reject.
        $qty = [
            'input_qty' => 0,
            'pass_qty' => (int) ($data['pass_qty'] ?? 0),
            'rework_qty' => $qc ? 0 : (int) ($data['rework_qty'] ?? 0),
            'reject_qty' => (int) ($data['reject_qty'] ?? 0),
        ];
        $defects = collect($data['defects'] ?? [])
            ->filter(fn ($d) => (int) ($d['qty'] ?? 0) > 0 && in_array($d['type'] ?? null, $qc ? ['reject'] : ['reject', 'rework'], true))->values();

        $po = OrderPo::with('order')->findOrFail($data['order_po_id']);

        $entry = DB::transaction(function () use ($po, $stage, $qc, $part, $qty, $defects, $data, $usesLine) {
            OrderPo::query()->whereKey($po->id)->lockForUpdate()->first();

            $errors = $this->balanceErrors($po, $stage, $qc, $part, (int) $data['size_id'], $qty) + $this->flow->defectErrors($defects, $qty);
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $entry = Entry::create($qty + [
                'stage' => $stage,
                'kind' => $qc ? 'qc' : 'rework',
                'entry_date' => $data['entry_date'],
                'order_po_id' => $po->id,
                'size_id' => $data['size_id'],
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

        $what = $qc ? "QC — pass {$entry->pass_qty}, reject {$entry->reject_qty}" : "rework — found {$entry->rework_qty}, pass {$entry->pass_qty}, reject {$entry->reject_qty}";

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

    private function balanceErrors(OrderPo $po, string $stage, bool $qc, ?string $part, int $sizeId, array $qty): array
    {
        if (($po->order->status ?? null) !== 'confirmed') {
            return ['order_po_id' => 'Only a confirmed order can go into production.'];
        }
        if (! in_array($stage, $this->flow->route($po), true)) {
            return ['order_po_id' => 'This PO has no ' . strtolower(ProductionFlow::label($stage)) . ' on its route.'];
        }
        if (! $po->sizes()->where('size_id', $sizeId)->exists()) {
            return ['size_id' => 'This size is not in the PO.'];
        }

        // The size's balance and the PO total (older entries may have no size) — the smaller one counts.
        $size = $this->balance($po, $stage, $this->flow->summary($po, null, $sizeId), $part);
        $total = $this->balance($po, $stage, $this->flow->summary($po), $part);
        $label = ProductionFlow::label($stage) . ($part ? " ({$part})" : '') . ' size ' . (Size::find($sizeId)->name ?? '');

        $ready = min($size['ready'], $total['ready']);
        if ($qc) {
            if ($qty['pass_qty'] + $qty['reject_qty'] === 0) {
                return ['pass_qty' => 'Enter the checked pass and / or reject quantity.'];
            }
            if ($qty['pass_qty'] + $qty['reject_qty'] > $ready) {
                return ['pass_qty' => 'Pass + reject is ' . ($qty['pass_qty'] + $qty['reject_qty']) . " pcs but only {$ready} passed pcs are still in {$label} (the rest moved on)."];
            }

            return [];
        }

        if ($qty['rework_qty'] + $qty['pass_qty'] + $qty['reject_qty'] === 0) {
            return ['rework_qty' => 'Enter the rework found and / or how many reworked pcs passed or were rejected.'];
        }
        if ($qty['rework_qty'] > $ready) {
            return ['rework_qty' => "Rework found is {$qty['rework_qty']} pcs but only {$ready} passed pcs are still in {$label} (the rest moved on)."];
        }
        // Fixed pieces come from what is waiting, including the rework found in this entry.
        $wip = min($size['wip'], $total['wip']) + $qty['rework_qty'];
        if ($qty['pass_qty'] + $qty['reject_qty'] > $wip) {
            return ['pass_qty' => 'Pass + reject is ' . ($qty['pass_qty'] + $qty['reject_qty']) . " pcs but only {$wip} pcs are in rework in {$label}."];
        }

        return [];
    }
}

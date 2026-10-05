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
 * Production → Reject & Rework: one screen for every step (cutting … packing).
 *   found (kind qc)       — reject / rework found among pieces the step already passed
 *   fixed (kind rework)   — rework pieces fixed: pass / reject
 * Saved as production entries of that stage, so status, reports and T&A count them.
 */
class RejectReworkController extends Controller
{
    public function __construct(private readonly ProductionFlow $flow)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_prod_entry.list');

        $entries = Entry::query()->whereIn('kind', ['qc', 'rework'])
            ->with(['orderPo.order.buyer', 'orderPo.style', 'orderPo.color', 'size', 'line.floorLine', 'defects', 'creator'])
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->stage))
            ->when($request->filled('order_po_id'), fn ($q) => $q->where('order_po_id', $request->order_po_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->to))
            ->latest('entry_date')->latest('id')->paginate(25)->withQueryString();

        return view('merchandising-sfl::admin.production.reject-rework.index', [
            'entries' => $entries,
            'pos' => Lookups::productionPos(),
            'stages' => self::stages(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_prod_entry.add');

        $pos = Lookups::productionPos();
        // po => stage => [ready (passed, still here), wip (incl. rework waiting)]
        $balances = $pos->mapWithKeys(function (OrderPo $po) {
            $summary = $this->flow->summary($po);

            return [$po->id => collect($summary)->map(fn ($row, $stage) => ['ready' => $this->flow->ready($po, $stage, $summary), 'wip' => $row['wip']])];
        });

        return view('merchandising-sfl::admin.production.reject-rework.create', [
            'pos' => $pos,
            'balances' => $balances,
            'stages' => self::stages(),
            'sizes' => Size::query()->orderBy('sort_order')->get(['id', 'name']),
            'lines' => Lookups::lines(),
            'machines' => ProductionFlow::machines(),
            'selectedPo' => $request->integer('order_po_id') ?: null,
            'selectedStage' => $request->query('stage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.add');

        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys(self::stages()))],
            'kind' => ['required', Rule::in(['qc', 'rework'])],
            'order_po_id' => ['required', Rule::exists('msfl_order_pos', 'id')],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'size_id' => ['nullable', Rule::exists('inv_sizes', 'id')],
            'line_id' => [Rule::requiredIf(ProductionFlow::usesLine((string) $request->stage)), 'nullable', Rule::exists('msfl_lines', 'id')->whereNull('deleted_at')],
            'pass_qty' => ['nullable', 'integer', 'min:0'],
            'rework_qty' => ['nullable', 'integer', 'min:0'],
            'reject_qty' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'defects' => ['nullable', 'array'],
            'defects.*.type' => ['nullable', 'in:reject,rework'],
            'defects.*.part_name' => ['nullable', 'string', 'max:100'],
            'defects.*.machine_id' => ['nullable', 'integer', Rule::exists('inv_machines', 'id')],
            'defects.*.defect' => ['nullable', 'string', 'max:150'],
            'defects.*.qty' => ['nullable', 'integer', 'min:0'],
        ]);

        $stage = $data['stage'];
        $found = $data['kind'] === 'qc';
        $usesLine = ProductionFlow::usesLine($stage);
        // Found: reject + rework out of passed pieces. Fixed: pass + reject out of the rework waiting.
        $qty = [
            'input_qty' => 0,
            'pass_qty' => $found ? 0 : (int) ($data['pass_qty'] ?? 0),
            'rework_qty' => $found ? (int) ($data['rework_qty'] ?? 0) : 0,
            'reject_qty' => (int) ($data['reject_qty'] ?? 0),
        ];
        $defects = collect($data['defects'] ?? [])
            ->filter(fn ($d) => (int) ($d['qty'] ?? 0) > 0 && in_array($d['type'] ?? null, $found ? ['reject', 'rework'] : ['reject'], true))->values();

        $po = OrderPo::with('order')->findOrFail($data['order_po_id']);

        $entry = DB::transaction(function () use ($po, $stage, $found, $qty, $defects, $data, $usesLine) {
            OrderPo::query()->whereKey($po->id)->lockForUpdate()->first();

            $errors = $this->balanceErrors($po, $stage, $found, $qty) + $this->flow->defectErrors($defects, $qty);
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $entry = Entry::create($qty + [
                'stage' => $stage,
                'kind' => $found ? 'qc' : 'rework',
                'entry_date' => $data['entry_date'],
                'order_po_id' => $po->id,
                'size_id' => $data['size_id'] ?? null,
                'line_id' => $usesLine ? $data['line_id'] : null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);
            foreach ($defects as $d) {
                $entry->defects()->create([
                    'type' => $d['type'],
                    'part_name' => filled($d['part_name'] ?? null) ? trim($d['part_name']) : null,
                    'machine_id' => $d['machine_id'] ?? null,
                    'defect' => $d['defect'] ?? null,
                    'qty' => (int) $d['qty'],
                ]);
            }

            return $entry;
        });

        $what = $found ? "reject {$entry->reject_qty}, rework {$entry->rework_qty}" : "rework fixed — pass {$entry->pass_qty}, reject {$entry->reject_qty}";

        return redirect()->route($request->boolean('add_another') ? 'msfl.production.reject-rework.create' : 'msfl.production.reject-rework.index',
            $request->boolean('add_another') ? ['order_po_id' => $po->id, 'stage' => $stage] : [])
            ->with('success', ProductionFlow::label($stage) . " saved: {$what}.");
    }

    public function destroy(Entry $entry): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.delete');
        abort_unless(in_array($entry->kind, ['qc', 'rework'], true), 404);

        if ($error = $this->flow->deleteBlocked($entry)) {
            return back()->with('error', $error);
        }

        $entry->delete();

        return back()->with('success', ProductionFlow::label($entry->stage) . ' reject / rework entry deleted.');
    }

    /** Every step a reject / rework can happen at, in route order. */
    public static function stages(): array
    {
        return ['cutting' => 'Cutting'] + collect(ProductionFlow::STAGES)->map(fn ($s) => $s[0])->all();
    }

    private function balanceErrors(OrderPo $po, string $stage, bool $found, array $qty): array
    {
        if (($po->order->status ?? null) !== 'confirmed') {
            return ['order_po_id' => 'Only a confirmed order can go into production.'];
        }
        if (! in_array($stage, $this->flow->route($po), true)) {
            return ['stage' => 'This PO has no ' . strtolower(ProductionFlow::label($stage)) . ' on its route.'];
        }

        $summary = $this->flow->summary($po);
        $label = ProductionFlow::label($stage);

        if ($found) {
            $ready = $this->flow->ready($po, $stage, $summary);
            if ($qty['reject_qty'] + $qty['rework_qty'] === 0) {
                return ['reject_qty' => 'Enter the reject or rework quantity.'];
            }
            if ($qty['reject_qty'] + $qty['rework_qty'] > $ready) {
                return ['reject_qty' => "Reject + rework is " . ($qty['reject_qty'] + $qty['rework_qty']) . " pcs but only {$ready} passed pcs are still in {$label} (the rest moved on)."];
            }

            return [];
        }

        $wip = $summary[$stage]['wip'];
        if ($qty['pass_qty'] + $qty['reject_qty'] === 0) {
            return ['pass_qty' => 'Enter how many reworked pcs passed or were rejected.'];
        }
        if ($qty['pass_qty'] + $qty['reject_qty'] > $wip) {
            return ['pass_qty' => "Pass + reject is " . ($qty['pass_qty'] + $qty['reject_qty']) . " pcs but only {$wip} pcs are waiting in {$label}."];
        }

        return [];
    }
}

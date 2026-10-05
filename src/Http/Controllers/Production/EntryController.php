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
 * Production → Embroidery / Sewing / Washing / Finishing / Final QC / Packing.
 * One screen per stage, same entry: pieces in, then QC — pass, rework, reject.
 * Every reject / rework count is broken down into defect rows (part, and for
 * sewing the Inventory machine) that add up to it.
 */
class EntryController extends Controller
{
    public function __construct(private readonly ProductionFlow $flow)
    {
    }

    public function index(Request $request, string $stage): View
    {
        $this->authorize('msfl_prod_entry.list');

        $entries = Entry::query()->where('stage', $stage)
            ->with(['orderPo.order.buyer', 'orderPo.style', 'orderPo.color', 'size', 'line.floorLine', 'defects', 'creator'])
            ->when($request->filled('order_po_id'), fn ($q) => $q->where('order_po_id', $request->order_po_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->to))
            ->latest('entry_date')->latest('id')->paginate(25)->withQueryString();

        return view('merchandising-sfl::admin.production.entries.index', [
            'stage' => $stage,
            'entries' => $entries,
            'pos' => Lookups::productionPos(),
        ]);
    }

    public function create(Request $request, string $stage): View
    {
        $this->authorize('msfl_prod_entry.add');

        $pos = Lookups::productionPos()->filter(fn (OrderPo $po) => in_array($stage, $this->flow->route($po), true))->values();
        $balances = $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => $this->flow->summary($po)[$stage]]);

        return view('merchandising-sfl::admin.production.entries.create', [
            'stage' => $stage,
            'pos' => $pos,
            'balances' => $balances,
            'sizes' => Size::query()->orderBy('sort_order')->get(['id', 'name']),
            'lines' => ProductionFlow::usesLine($stage) ? Lookups::lines() : collect(),
            'machines' => ProductionFlow::usesLine($stage) ? $this->machines() : collect(),
            'selectedPo' => $request->integer('order_po_id') ?: null,
        ]);
    }

    public function store(Request $request, string $stage): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.add');

        $usesLine = ProductionFlow::usesLine($stage);
        $data = $request->validate([
            'order_po_id' => ['required', Rule::exists('msfl_order_pos', 'id')],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'size_id' => ['nullable', Rule::exists('inv_sizes', 'id')],
            'line_id' => [$usesLine ? 'required' : 'nullable', Rule::exists('msfl_lines', 'id')->whereNull('deleted_at')],
            'input_qty' => ['nullable', 'integer', 'min:0'],
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

        $qty = collect(['input_qty', 'pass_qty', 'rework_qty', 'reject_qty'])->mapWithKeys(fn ($k) => [$k => (int) ($data[$k] ?? 0)])->all();
        $defects = collect($data['defects'] ?? [])->filter(fn ($d) => (int) ($d['qty'] ?? 0) > 0 && in_array($d['type'] ?? null, ['reject', 'rework'], true))->values();

        $po = OrderPo::with('order')->findOrFail($data['order_po_id']);

        $entry = DB::transaction(function () use ($po, $stage, $qty, $defects, $data, $usesLine) {
            // Lock the PO so two entries can't both pass the balance check.
            OrderPo::query()->whereKey($po->id)->lockForUpdate()->first();

            $errors = ($po->order->status ?? null) === 'confirmed'
                ? $this->flow->validate($po, $stage, $qty)
                : ['order_po_id' => 'Only a confirmed order can go into production.'];
            $errors += $this->defectErrors($defects, $qty, $usesLine);
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $entry = Entry::create($qty + [
                'stage' => $stage,
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
                    'machine_id' => $usesLine ? ($d['machine_id'] ?? null) : null,
                    'defect' => $d['defect'] ?? null,
                    'qty' => (int) $d['qty'],
                ]);
            }

            return $entry;
        });

        $label = ProductionFlow::label($stage);

        return redirect()->route($request->boolean('add_another') ? 'msfl.production.entries.create' : 'msfl.production.entries.index', ['stage' => $stage] + ($request->boolean('add_another') ? ['order_po_id' => $po->id] : []))
            ->with('success', "{$label} entry saved: in {$entry->input_qty}, pass {$entry->pass_qty}, rework {$entry->rework_qty}, reject {$entry->reject_qty}.");
    }

    public function destroy(string $stage, Entry $entry): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.delete');
        abort_unless($entry->stage === $stage, 404);

        // Deleting this entry's passed pieces mustn't leave the next stage with more than it got.
        $po = $entry->orderPo;
        $route = $this->flow->route($po);
        $next = $route[array_search($stage, $route, true) + 1] ?? null;
        if ($next && $entry->pass_qty > 0) {
            $after = $this->flow->summary($po, $entry->id);
            if ($after[$next]['input'] > $after[$stage]['pass']) {
                return back()->with('error', ProductionFlow::label($next) . ' has already taken in these pieces — delete its entries first.');
            }
        }

        $entry->delete();

        return back()->with('success', ProductionFlow::label($stage) . ' entry deleted.');
    }

    /**
     * Reject / rework counts must be explained: defect rows of that type add
     * up to the count; sewing rejects name the part and the machine.
     */
    private function defectErrors($defects, array $qty, bool $usesLine): array
    {
        $errors = [];
        foreach (['reject' => 'reject_qty', 'rework' => 'rework_qty'] as $type => $field) {
            $rows = $defects->where('type', $type);
            $sum = (int) $rows->sum(fn ($d) => (int) $d['qty']);
            if ($qty[$field] > 0 && $sum !== $qty[$field]) {
                $errors['defects'] = ucfirst($type) . " is {$qty[$field]} pcs but the {$type} rows add up to {$sum} — say which part"
                    . ($usesLine ? ' / machine' : '') . ' each one is.';
            } elseif ($qty[$field] === 0 && $sum > 0) {
                $errors['defects'] = "There are {$type} rows but the {$type} quantity is 0.";
            }
        }
        if ($usesLine && $defects->where('type', 'reject')->contains(fn ($d) => blank($d['part_name'] ?? null) || blank($d['machine_id'] ?? null))) {
            $errors['defects'] = 'Each sewing reject row needs the part and the machine.';
        }

        return $errors;
    }

    /** Active Inventory machines, with the line text they are on (to filter by the chosen line). */
    private function machines()
    {
        if (! class_exists(\ME\SflInventory\Models\InvMachine::class)) {
            return collect();
        }

        return DB::table('inv_machines')->whereNull('deleted_at')->where('is_active', true)
            ->orderBy('code')->get(['id', 'code', 'name', 'type', 'line']);
    }
}

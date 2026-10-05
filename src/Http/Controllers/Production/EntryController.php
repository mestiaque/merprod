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
 * Production → Embroidery (part-wise) / Sewing / Washing / Finishing / Buyer QC / Packing.
 * One screen per stage, same entry: pieces in, then QC — pass, rework, reject.
 * Every reject / rework count is broken down into defect rows (part, and for
 * the Inventory machine, optional) that add up to it.
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

        $pos = Lookups::productionPos()->load('sizes')->filter(fn (OrderPo $po) => in_array($stage, $this->flow->route($po), true))->values();
        // po => size => balance (size-wise flow); part-wise stages add parts / newPart.
        $balances = $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => $this->flow->sizeBalances($po, $stage)]);

        return view('merchandising-sfl::admin.production.entries.create', [
            'stage' => $stage,
            'pos' => $pos,
            'balances' => $balances,
            'sizes' => Size::query()->orderBy('sort_order')->get(['id', 'name']),
            'poSizes' => $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => $po->sizes->pluck('size_id')->all()]),
            'lines' => ProductionFlow::usesLine($stage) ? Lookups::lines() : collect(),
            'machines' => ProductionFlow::machines(),
            'selectedPo' => $request->integer('order_po_id') ?: null,
            'partWise' => in_array($stage, ProductionFlow::PART_STAGES, true),
            'poParts' => ProductionFlow::poParts($pos->pluck('id')),
            'garmentParts' => Lookups::garmentParts(),
        ]);
    }

    public function store(Request $request, string $stage): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.add');

        $usesLine = ProductionFlow::usesLine($stage);
        $data = $request->validate([
            'order_po_id' => ['required', Rule::exists('msfl_order_pos', 'id')],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'size_id' => ['required', Rule::exists('inv_sizes', 'id')],
            'part_name' => [in_array($stage, ProductionFlow::PART_STAGES, true) ? 'required' : 'nullable', 'string', 'max:100', Rule::exists('msfl_garment_parts', 'name')->whereNull('deleted_at')],
            'line_id' => [$usesLine ? 'required' : 'nullable', Rule::exists('msfl_lines', 'id')->whereNull('deleted_at')],
            'input_qty' => ['nullable', 'integer', 'min:0'],
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

        $qty = collect(['input_qty', 'pass_qty', 'rework_qty', 'reject_qty'])->mapWithKeys(fn ($k) => [$k => (int) ($data[$k] ?? 0)])->all();
        $defects = collect($data['defects'] ?? [])->filter(fn ($d) => (int) ($d['qty'] ?? 0) > 0 && in_array($d['type'] ?? null, ['reject', 'rework'], true))->values();

        $po = OrderPo::with('order')->findOrFail($data['order_po_id']);
        if (! $po->sizes()->where('size_id', $data['size_id'])->exists()) {
            throw ValidationException::withMessages(['size_id' => 'This size is not in the PO.']);
        }

        $entry = DB::transaction(function () use ($po, $stage, $qty, $defects, $data, $usesLine) {
            // Lock the PO so two entries can't both pass the balance check.
            OrderPo::query()->whereKey($po->id)->lockForUpdate()->first();

            $errors = ($po->order->status ?? null) === 'confirmed'
                ? $this->flow->validate($po, $stage, $qty, null, $data['part_name'] ?? null, (int) $data['size_id'])
                : ['order_po_id' => 'Only a confirmed order can go into production.'];
            $errors += $this->flow->defectErrors($defects, $qty);
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $entry = Entry::create($qty + [
                'stage' => $stage,
                'entry_date' => $data['entry_date'],
                'order_po_id' => $po->id,
                'size_id' => $data['size_id'],
                'part_name' => in_array($stage, ProductionFlow::PART_STAGES, true) ? trim($data['part_name']) : null,
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

        $label = ProductionFlow::label($stage);

        return redirect()->route($request->boolean('add_another') ? 'msfl.production.entries.create' : 'msfl.production.entries.index', ['stage' => $stage] + ($request->boolean('add_another') ? ['order_po_id' => $po->id] : []))
            ->with('success', "{$label}" . ($entry->part_name ? " ({$entry->part_name})" : '') . " entry saved: in {$entry->input_qty}, pass {$entry->pass_qty}, rework {$entry->rework_qty}, reject {$entry->reject_qty}.");
    }

    public function destroy(string $stage, Entry $entry): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.delete');
        abort_unless($entry->stage === $stage, 404);

        // Deleting this entry's passed pieces mustn't leave the next stage with more than it got.
        if ($error = $this->flow->deleteBlocked($entry)) {
            return back()->with('error', $error);
        }

        $entry->delete();

        return back()->with('success', ProductionFlow::label($stage) . ' entry deleted.');
    }
}

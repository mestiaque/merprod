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
use ME\MerchandisingSfl\Models\Production\FabricRequisition;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Production → Fabric Requisition. Raises a normal Inventory requisition for
 * an order PO; the store approves and issues it in Inventory as usual, and
 * the requested / issued quantities are read back here.
 *
 * From the Buyer Store the requisition carries the Inventory buyer and the
 * style no, so the store can only issue fabric received for that style.
 */
class FabricRequisitionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('msfl_prod_requisition.list');

        $requisitions = FabricRequisition::query()
            ->with(['orderPo.order.buyer', 'orderPo.style', 'orderPo.color', 'requisition.store', 'requisition.items.item', 'creator'])
            ->when($request->filled('order_po_id'), fn ($q) => $q->where('order_po_id', $request->order_po_id))
            ->latest('id')->paginate(20)->withQueryString();

        return view('merchandising-sfl::admin.production.requisitions.index', [
            'requisitions' => $requisitions,
            'pos' => Lookups::productionPos(),
            'available' => $this->inventoryAvailable(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('msfl_prod_requisition.add');

        if (! $this->inventoryAvailable()) {
            return redirect()->route('msfl.production.requisitions.index')->with('error', 'The Inventory package is not installed.');
        }

        return view('merchandising-sfl::admin.production.requisitions.create', [
            'pos' => Lookups::productionPos(),
            'stores' => \ME\SflInventory\Models\InvStore::query()
                ->whereIn('type', [\ME\SflInventory\Models\InvStore::TYPE_BUYER, \ME\SflInventory\Models\InvStore::TYPE_GENERAL])
                ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'purposes' => \ME\SflInventory\Models\InvRequisitionPurpose::active()->orderBy('name')->pluck('name', 'code'),
            'departments' => \ME\SflInventory\Models\InvDepartment::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'items' => \ME\SflInventory\Models\InvItem::query()->active()->with('unit:id,short_name,name')
                ->orderBy('item_name')->get(['id', 'item_code', 'item_name', 'unit_id']),
            'selectedPo' => $request->integer('order_po_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('msfl_prod_requisition.add');
        abort_unless($this->inventoryAvailable(), 404);

        $data = $request->validate([
            'order_po_id' => ['required', Rule::exists('msfl_order_pos', 'id')],
            'requisition_date' => ['required', 'date'],
            'store_id' => ['required', Rule::exists('inv_stores', 'id')->whereNull('deleted_at')],
            'department_id' => ['required', Rule::exists('inv_departments', 'id')],
            'requisition_for' => ['nullable', Rule::in(\ME\SflInventory\Models\InvRequisitionPurpose::active()->pluck('code')->all())],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', Rule::exists('inv_items', 'id')->whereNull('deleted_at')],
            'items.*.requested_qty' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lines = collect($data['items'])->filter(fn ($l) => filled($l['item_id'] ?? null) && (float) ($l['requested_qty'] ?? 0) > 0);
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Add at least one item with a quantity.']);
        }

        $po = OrderPo::with(['order.buyer', 'style', 'color'])->findOrFail($data['order_po_id']);
        if (($po->order->status ?? null) !== 'confirmed') {
            throw ValidationException::withMessages(['order_po_id' => 'Only a confirmed order can go into production.']);
        }
        $store = \ME\SflInventory\Models\InvStore::findOrFail($data['store_id']);
        $buyerStore = $store->type === \ME\SflInventory\Models\InvStore::TYPE_BUYER;

        $requisition = DB::transaction(function () use ($data, $lines, $po, $buyerStore) {
            $requisition = \ME\SflInventory\Models\InvRequisition::create([
                'requisition_date' => $data['requisition_date'],
                'department_id' => $data['department_id'],
                'requisition_for' => $data['requisition_for'] ?? null,
                'store_id' => $data['store_id'],
                'buyer_id' => $buyerStore ? app(\ME\SflInventory\Services\MerchandisingLink::class)->inventoryBuyerId($po->order->buyer_id) : null,
                'style' => $buyerStore ? $po->style->style_no : null,
                'order_ref' => $po->label(),
                'msfl_buyer_id' => $po->order->buyer_id,
                'msfl_style_id' => $po->style_id,
                'msfl_order_po_id' => $po->id,
                'requested_by' => auth()->id(),
                'status' => 'pending',
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $item = \ME\SflInventory\Models\InvItem::find($line['item_id']);
                [$colorId, $sizeId] = $item?->resolvedVariant(null, null) ?? [null, null];
                $requisition->items()->create([
                    'item_id' => $line['item_id'],
                    'color_id' => $colorId,
                    'size_id' => $sizeId,
                    'requested_qty' => $line['requested_qty'],
                ]);
            }

            FabricRequisition::create(['order_po_id' => $po->id, 'inv_requisition_id' => $requisition->id, 'created_by' => auth()->id()]);

            return $requisition;
        });

        // Same central approval Inventory raises for its own requisitions.
        if (class_exists(\App\Services\ApprovalService::class)) {
            $requisition->loadMissing(['requester', 'department', 'store']);
            app(\App\Services\ApprovalService::class)->request([
                'module' => 'inventory.requisition',
                'approvable' => $requisition,
                'title' => "Requisition Approval - {$requisition->requisition_no}",
                'description' => "{$requisition->requester?->name} requested materials for {$po->label()} from {$requisition->department?->name} / {$requisition->store?->name}.",
                'route_name' => 'inventory.requisitions.approval-form',
                'route_params' => ['requisition' => $requisition->id],
                'requested_by' => auth()->id(),
            ]);
        }

        return redirect()->route('msfl.production.requisitions.index')
            ->with('success', "Requisition {$requisition->requisition_no} sent to {$store->name} for approval and issue.");
    }

    private function inventoryAvailable(): bool
    {
        return class_exists(\ME\SflInventory\Models\InvRequisition::class);
    }
}

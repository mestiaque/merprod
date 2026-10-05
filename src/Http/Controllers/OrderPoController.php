<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Http\Requests\OrderPoRequest;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\OrderPo;

/** PO lines (PO + style + color, with size breakdown) of an order. */
class OrderPoController extends Controller
{
    public function store(OrderPoRequest $request, Order $order): RedirectResponse
    {
        abort_unless($order->isEditable(), 403);
        $data = $request->validated();

        DB::transaction(function () use ($order, $data) {
            $po = $order->pos()->create(Arr::except($data, 'sizes'));
            $po->syncSizes($data['sizes']);
            $order->refreshTotals();
        });

        return back()->with('success', 'PO line added.');
    }

    public function update(OrderPoRequest $request, Order $order, OrderPo $po): RedirectResponse
    {
        abort_unless($po->order_id === $order->id && $order->isEditable(), 403);
        $data = $request->validated();

        DB::transaction(function () use ($order, $po, $data) {
            $before = ['po_qty' => (string) $po->po_qty, 'pcd_date' => $po->pcd_date?->toDateString(), 'shipment_date' => $po->shipment_date?->toDateString()];

            $po->update(Arr::except($data, 'sizes'));
            $po->syncSizes($data['sizes']);
            $order->refreshTotals();

            // After confirmation a change is a buyer revision — keep the history (T&A sheet "revised" columns).
            if ($order->status === 'confirmed') {
                $po->refresh();
                $after = ['po_qty' => (string) $po->po_qty, 'pcd_date' => $po->pcd_date?->toDateString(), 'shipment_date' => $po->shipment_date?->toDateString()];
                foreach ($after as $field => $value) {
                    if ($value !== $before[$field]) {
                        $po->revisions()->create(['field' => $field, 'old_value' => $before[$field], 'new_value' => $value, 'changed_by' => auth()->id(), 'changed_at' => now()]);
                    }
                }
            }
        });

        return back()->with('success', 'PO line updated.');
    }

    public function destroy(Order $order, OrderPo $po): RedirectResponse
    {
        $this->authorize('msfl_order.edit');
        abort_unless($po->order_id === $order->id && $order->isEditable(), 403);

        DB::transaction(function () use ($order, $po) {
            $po->delete();
            $order->refreshTotals();
        });

        return back()->with('success', 'PO line deleted.');
    }
}

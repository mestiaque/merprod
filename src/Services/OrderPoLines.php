<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\OrderPo;

/**
 * Writes an order's PO lines (PO + style + color with size breakdown): one line
 * (OrderPoController) or all of them at once (the one-page order form).
 * After confirmation a change of qty / PCD / shipment date is a buyer revision
 * (msfl_order_po_revisions → T&A sheet "revised" columns).
 */
class OrderPoLines
{
    /** Tables that tie a PO to work already done — such a PO can't be removed. */
    private const USED_IN = [
        'msfl_prod_cuttings' => 'cutting', 'msfl_prod_entries' => 'production', 'msfl_prod_sewing_plans' => 'sewing plan',
        'msfl_prod_fabric_requisitions' => 'fabric requisition', 'inv_requisitions' => 'Inventory requisition', 'inv_issues' => 'Inventory issue',
        'inv_grns' => 'Inventory receive', 'inv_finished_goods_receives' => 'Finish Store receive', 'inv_shipment_items' => 'shipment',
    ];

    /** Create ($po null) or update one line; $data = OrderPo fields + 'sizes' => [size_id => qty]. */
    public function save(Order $order, ?OrderPo $po, array $data): OrderPo
    {
        $fields = Arr::except($data, ['id', 'sizes']);

        if (! $po) {
            $po = $order->pos()->create($fields);
            $po->syncSizes($data['sizes']);

            return $po;
        }

        $before = $this->tracked($po);
        $po->update($fields);
        $po->syncSizes($data['sizes']);

        if ($order->status === 'confirmed') {
            foreach ($this->tracked($po->refresh()) as $field => $value) {
                if ($value !== $before[$field]) {
                    $po->revisions()->create(['field' => $field, 'old_value' => $before[$field], 'new_value' => $value, 'changed_by' => auth()->id(), 'changed_at' => now()]);
                }
            }
        }

        return $po;
    }

    /**
     * Make the order's lines exactly $lines (each with an optional 'id' of an existing line):
     * update those with an id, add the rest, delete existing lines left out.
     */
    public function sync(Order $order, array $lines): void
    {
        DB::transaction(function () use ($order, $lines) {
            $existing = $order->pos()->get()->keyBy('id');
            $kept = [];
            foreach ($lines as $line) {
                $po = ! empty($line['id']) ? $existing->get((int) $line['id']) : null;
                $kept[] = $this->save($order, $po, $line)->id;
            }
            $existing->except($kept)->each->delete();
            $order->refreshTotals();
        });
    }

    /** Why the PO can't be removed (work already recorded against it), or null. */
    public function usedIn(OrderPo $po): ?string
    {
        foreach (self::USED_IN as $table => $label) {
            $column = str_starts_with($table, 'inv_') ? 'msfl_order_po_id' : 'order_po_id';
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            $query = DB::table($table)->where($column, $po->id)->when(Schema::hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'));
            if ($query->exists()) {
                return "PO {$po->po_no} (" . ($po->style->style_no ?? '') . ' · ' . ($po->color->name ?? '') . ") already has {$label} — it cannot be removed.";
            }
        }

        return null;
    }

    private function tracked(OrderPo $po): array
    {
        return ['po_qty' => (string) $po->po_qty, 'pcd_date' => $po->pcd_date?->toDateString(), 'shipment_date' => $po->shipment_date?->toDateString()];
    }
}

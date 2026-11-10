<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\Bom;

/**
 * Material Requirement Plan (MRP) — fabrics, trims & packing items. One row per style × BOM line.
 *
 * Requirement comes from the style's BOM (latest approved version, else the latest draft):
 * Order Qty (open POs of the style, narrowed to the BOM line's colour / size) × consumption per piece
 * = Gross; + wastage % = Net.
 *
 * Stock is split on purpose:
 *  - Style Stock = received − issued for this style in Inventory (GRNs / requisitions kept against the
 *    style, matched like MaterialShortage). Only this is netted off the requirement.
 *  - General Stock (shared) = everything in the General Store for the item. Shown for information only:
 *    it belongs to no style, so taking it off every style's shortage would count the same stock many times.
 *
 * A Merchandising item (msfl_items) has no link to the Inventory item, so the two are matched by
 * code (msfl_items.code = inv_items.item_code); an unmatched item shows no stock / received figures.
 *
 * NOT YET STORED — typed in on the sheet until the data exists:
 *  - On Order: Inventory Store Orders carry no style, so it can't be worked out per style.
 *  - PO / Booking Ref, Expected In-House, Inspection Status.
 */
class MaterialRequirementPlan extends OpsReport
{
    public function key(): string
    {
        return 'material-requirement';
    }

    public function title(): string
    {
        return 'Material Requirement Plan (MRP)';
    }

    public function department(): string
    {
        return 'Merchandising / Store';
    }

    public function guidance(): string
    {
        return 'One row per style × BOM line (latest approved BOM, else the latest draft). Gross = Order Qty × Consumption; Net = Gross + Wastage %. '
            . 'Style Stock = received − issued for the style in Inventory; Shortage = Net − Style Stock − On Order. '
            . 'General Stock (shared) is the General Store total for the item — for information only, not taken off the shortage. '
            . 'Status: Critical ≤ 3 days to the Required Date, High ≤ 7, Medium otherwise, OK when nothing is short. '
            . 'Inventory items are matched to BOM items by item code. Rows: styles with a PO not fully packed.';
    }

    public function filters(): array
    {
        return ['buyer', 'style'];
    }

    public function columns(): array
    {
        return [
            self::col('buyer', 'Buyer'), self::col('style_no', 'Style'), self::col('po_no', 'PO'),
            self::col('category', 'Item Category'), self::col('material', 'Material Description'), self::col('item_code', 'Item Code'),
            self::col('color', 'Color'), self::col('size', 'Size / Width'), self::col('uom', 'UOM'),
            self::col('order_qty', 'Order Qty', 'auto', 'int'),
            self::col('consumption', 'Consumption / Pc', 'auto', 'num'),
            self::col('gross', 'Gross Requirement', 'formula', 'num'),
            self::col('wastage', 'Wastage %', 'auto', 'pct'),
            self::col('net', 'Net Requirement', 'formula', 'num'),
            self::col('style_stock', 'Style Stock', 'auto', 'num'),
            self::col('on_order', 'On Order', 'input', 'num'),
            self::col('shortage', 'Shortage Qty', 'formula', 'num'),
            self::col('general_stock', 'General Stock (shared)', 'auto', 'num'),
            self::col('supplier', 'Supplier', 'auto_input'),
            self::col('booking_ref', 'PO / Booking Ref', 'input'),
            self::col('required_by', 'Required Date', 'auto_input', 'date'),
            self::col('expected_inhouse', 'Expected In-House', 'input', 'date'),
            self::col('status', 'Status', 'formula', 'text', ['status' => true]),
            self::col('received', 'Received Qty', 'auto', 'num'),
            self::col('inspection', 'Inspection Status', 'input', 'select', ['options' => ['Pending', 'Passed', 'Failed', 'Re-inspection'], 'status' => true]),
            self::col('approved', 'Approved Qty', 'auto', 'num'),
            self::col('remarks', 'Remarks', 'input'),
        ];
    }

    protected function rows(Request $r, Carbon $date, array $settings): array
    {
        $pos = $this->pos($r)->with('sizes')->when($r->filled('style_id'), fn ($q) => $q->where('style_id', $r->style_id))->get();
        $packed = $this->netPass($pos->pluck('id'));
        $open = $pos->filter(fn ($po) => ($packed[$po->id]['packing'] ?? 0) < $po->po_qty)->groupBy('style_id');
        if ($open->isEmpty()) {
            return [];
        }

        // Latest approved BOM per style, else the latest one.
        $boms = Bom::query()->with(['items.item.category', 'items.item.uom', 'items.color', 'items.size', 'items.uom', 'items.supplier'])
            ->whereIn('style_id', $open->keys())
            ->orderByRaw("status = 'approved' DESC")->orderByDesc('version')->orderByDesc('id')
            ->get()->unique('style_id')->keyBy('style_id');
        if ($boms->isEmpty()) {
            return [];
        }

        // Inventory item per BOM item, by code.
        $codes = $boms->flatMap(fn ($b) => $b->items->map(fn ($i) => trim((string) $i->item?->code)))->filter()->unique();
        $invItem = DB::table('inv_items')->whereNull('deleted_at')->whereIn(DB::raw('TRIM(item_code)'), $codes)
            ->pluck('id', DB::raw('TRIM(item_code) as code'))->mapWithKeys(fn ($id, $code) => [mb_strtolower($code) => (int) $id]);

        [$byStyle, $suppliers] = $this->styleFigures($open, $invItem->values());
        $general = $this->generalStock($invItem->values());

        $rows = [];
        foreach ($boms as $styleId => $bom) {
            $g = $open[$styleId];
            if ($bom->order_id) {
                $g = $g->where('order_id', $bom->order_id);
            }
            if ($g->isEmpty()) {
                continue;
            }
            $style = $g->first()->style;
            foreach ($bom->items as $line) {
                $poLines = $line->color_id ? $g->where('color_id', $line->color_id) : $g;
                $orderQty = $line->size_id
                    ? (int) $poLines->sum(fn ($po) => $po->sizes->where('size_id', $line->size_id)->sum('qty'))
                    : (int) $poLines->sum('po_qty');
                $inv = $invItem[mb_strtolower(trim((string) $line->item?->code))] ?? null;
                $f = $inv ? ($byStyle[$styleId . '|' . $inv] ?? []) : [];

                $rows[] = [
                    '_key' => 'b:' . $line->id, '_label' => ($style->style_no ?? '') . ' · ' . ($line->item->name ?? $line->item_id),
                    'buyer' => $g->first()->order->buyer->name ?? '', 'style_no' => $style->style_no ?? '',
                    'po_no' => $poLines->pluck('po_no')->unique()->implode(', '),
                    'category' => $line->item->category->name ?? '',
                    'material' => trim(($line->item->name ?? '') . ($line->placement ? ' — ' . $line->placement : '')),
                    'item_code' => $line->item->code ?? '',
                    'color' => $line->color->name ?? '', 'size' => $line->size->name ?? '',
                    'uom' => $line->uom->short_name ?? $line->uom->name ?? $line->item->uom->short_name ?? $line->item->uom->name ?? '',
                    'order_qty' => $orderQty,
                    'consumption' => (float) $line->consumption,
                    'wastage' => (float) $line->wastage_percent,
                    'style_stock' => $inv ? round(($f['received'] ?? 0) - ($f['issued'] ?? 0), 2) : null,
                    'general_stock' => $inv ? round($general[$inv] ?? 0, 2) : null,
                    'supplier' => $line->supplier->name ?? (collect($suppliers[$styleId . '|' . $inv] ?? [])->unique()->implode(', ') ?: null),
                    'required_by' => $poLines->min(fn ($po) => $po->pcd_date?->toDateString()),
                    'received' => $inv ? round($f['received'] ?? 0, 2) : null,
                    'approved' => $inv ? round($f['approved'] ?? 0, 2) : null,
                ];
            }
        }

        return collect($rows)->sortBy(fn ($x) => $x['buyer'] . '|' . $x['style_no'] . '|' . $x['category'] . '|' . $x['material'])->values()->all();
    }

    /**
     * Received (posted GRNs), approved and issued (requisitions) per style × inventory item, matched to the
     * style the way MaterialShortage does: the Merchandising style, or the same buyer + style text.
     *
     * @return array{0: array<string, array{received?: float, approved?: float, issued?: float}>, 1: array<string, string[]>}
     */
    private function styleFigures($open, $invItemIds): array
    {
        if ($invItemIds->isEmpty()) {
            return [[], []];
        }
        $styles = $open->map(fn ($g) => $g->first()->style);
        $invBuyer = DB::table('inv_buyers')->whereNull('deleted_at')->get(['id', 'name'])
            ->mapWithKeys(fn ($b) => [mb_strtolower(trim($b->name)) => $b->id]);
        $byText = [];
        foreach ($open as $styleId => $g) {
            $buyer = $invBuyer[mb_strtolower(trim($g->first()->order->buyer->name ?? ''))] ?? null;
            $byText[($buyer ?? 'x') . '|' . trim($styles[$styleId]->style_no)] = $styleId;
        }
        $owner = fn ($x) => $x->msfl_style_id ? ($open->has($x->msfl_style_id) ? $x->msfl_style_id : null) : ($byText[$x->buyer_id . '|' . trim((string) $x->style)] ?? null);
        $match = fn ($q, $t) => $q->where(fn ($w) => $w->whereIn("{$t}.msfl_style_id", $open->keys())
            ->orWhere(fn ($o) => $o->whereNull("{$t}.msfl_style_id")->whereIn(DB::raw("TRIM({$t}.style)"), $styles->pluck('style_no')->map(fn ($s) => trim($s)))));

        $out = [];
        $suppliers = [];
        DB::table('inv_grn_items as gi')->join('inv_grns as g', 'g.id', '=', 'gi.grn_id')->leftJoin('inv_suppliers as su', 'su.id', '=', 'g.supplier_id')
            ->whereNull('g.deleted_at')->where('g.status', 'posted')->whereIn('gi.item_id', $invItemIds)->tap(fn ($q) => $match($q, 'g'))
            ->get(['g.msfl_style_id', 'g.buyer_id', 'g.style', 'g.source_type', 'su.name as supplier', 'gi.item_id', 'gi.received_qty'])
            ->each(function ($x) use ($owner, &$out, &$suppliers) {
                if ($sid = $owner($x)) {
                    $k = $sid . '|' . $x->item_id;
                    $out[$k]['received'] = ($out[$k]['received'] ?? 0) + (float) $x->received_qty;
                    $suppliers[$k][] = $x->source_type === 'buyer_supplied' ? 'Buyer supplied' : ($x->supplier ?: 'Supplier');
                }
            });
        DB::table('inv_requisition_items as ri')->join('inv_requisitions as rq', 'rq.id', '=', 'ri.requisition_id')
            ->whereNull('rq.deleted_at')->whereNotIn('rq.status', ['rejected', 'cancelled'])->whereIn('ri.item_id', $invItemIds)->tap(fn ($q) => $match($q, 'rq'))
            ->get(['rq.msfl_style_id', 'rq.buyer_id', 'rq.style', 'ri.item_id', 'ri.approved_qty', 'ri.issued_qty'])
            ->each(function ($x) use ($owner, &$out) {
                if ($sid = $owner($x)) {
                    $k = $sid . '|' . $x->item_id;
                    $out[$k]['approved'] = ($out[$k]['approved'] ?? 0) + (float) $x->approved_qty;
                    $out[$k]['issued'] = ($out[$k]['issued'] ?? 0) + (float) $x->issued_qty;
                }
            });

        return [$out, $suppliers];
    }

    /** Inventory item id => balance in the General Store(s), from the stock ledger. */
    private function generalStock($invItemIds): array
    {
        if ($invItemIds->isEmpty()) {
            return [];
        }
        // inv_stores.type 'accessories' = General Store (InvStore::TYPE_GENERAL in the inventory package).
        return DB::table('inv_stock_transactions as t')->join('inv_stores as s', 's.id', '=', 't.store_id')
            ->where('s.type', 'accessories')->whereIn('t.item_id', $invItemIds)
            ->groupBy('t.item_id')->selectRaw('t.item_id, SUM(t.qty_in) - SUM(t.qty_out) as bal')
            ->pluck('bal', 'item_id')->map(fn ($v) => (float) $v)->all();
    }

    protected function compute(array $rows, Carbon $date, array $settings): array
    {
        foreach ($rows as &$x) {
            $x['gross'] = round($x['order_qty'] * $x['consumption'], 2);
            $x['net'] = round($x['gross'] * (1 + $x['wastage'] / 100), 2);
            $x['shortage'] = max(0, round($x['net'] - (float) ($x['style_stock'] ?? 0) - (float) ($x['on_order'] ?? 0), 2));
            $days = $x['required_by'] ? (int) $date->diffInDays(Carbon::parse($x['required_by']), false) : null;
            $x['status'] = match (true) {
                ! $x['shortage'] => 'OK',
                $days !== null && $days <= 3 => 'Critical',
                $days !== null && $days <= 7 => 'High',
                default => 'Medium',
            };
        }

        return $rows;
    }

    public function kpis(array $rows): array
    {
        $c = collect($rows);

        return [
            ['Material Lines', self::fmt($c->count(), 'int')],
            ['Total Required', self::fmt($c->sum('net'), 'num')],
            ['Total Shortage', self::fmt($c->sum('shortage'), 'num')],
            ['Critical Items', self::fmt($c->where('status', 'Critical')->count(), 'int')],
        ];
    }

    public function signatures(): array
    {
        return ['Prepared By', 'Merchandiser', 'Store In-charge', 'Approved By'];
    }
}

<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\Entry;
use ME\MerchandisingSfl\Models\Sample;
use ME\MerchandisingSfl\Models\TnaTask;

/**
 * Merchandising v2 reports. Each returns
 *   ['headers' => [label, ...], 'align' => [i => 'right'], 'rows' => [[...], ...], 'totals' => [i => value] | null].
 * Filters come from the request: buyer_id, style_id, from, to, stage, line_id.
 */
class Reports
{
    /** key => [title, icon, filters used, description] */
    public const LIST = [
        'order-book' => ['Order Book', 'fa-book', ['buyer', 'dates'], 'Every PO line of running orders: qty, value, dates and how much is packed / in the Finish Store.'],
        'daily-production' => ['Daily Production', 'fa-industry', ['buyer', 'dates', 'stage', 'line'], 'Stage entries day by day: input, pass, rework, reject.'],
        'defects' => ['Defect Analysis', 'fa-triangle-exclamation', ['dates', 'stage', 'line'], 'Rejects and rework by stage, part, machine and defect.'],
        'shipment-status' => ['Shipment Status', 'fa-truck-fast', ['buyer', 'dates'], 'POs by shipment date with cut / sewn / packed progress and risk.'],
        'tna-status' => ['T&A Status', 'fa-calendar-check', ['buyer'], 'Open T&A tasks of running plans — overdue and due within 7 days.'],
        'sample-turnaround' => ['Sample Turnaround', 'fa-vial', ['buyer', 'dates'], 'Each sample: requested → submitted → decided, with days taken.'],
        'requisition-details' => ['Requisition Details', 'fa-dolly', ['buyer', 'style', 'dates'], 'Every fabric requisition item by buyer / style / PO: requested, approved, issued — and on which dates the store issued how much.'],
        'requisition-summary' => ['Requisition Summary', 'fa-boxes-stacked', ['buyer', 'style', 'dates'], 'Totals per buyer → style → item: requested, approved, issued, still to issue, first / last issue date.'],
    ];

    public function run(string $key, Request $r): array
    {
        $from = $r->filled('from') ? Carbon::parse($r->from)->startOfDay() : null;
        $to = $r->filled('to') ? Carbon::parse($r->to)->endOfDay() : null;

        return match ($key) {
            'order-book' => $this->orderBook($r, $from, $to),
            'daily-production' => $this->dailyProduction($r, $from ?? today()->subDays(6), $to ?? today()->endOfDay()),
            'defects' => $this->defects($r, $from ?? today()->subDays(29), $to ?? today()->endOfDay()),
            'shipment-status' => $this->shipmentStatus($r, $from ?? today()->subDays(7), $to ?? today()->addDays(60)),
            'tna-status' => $this->tnaStatus($r),
            'sample-turnaround' => $this->sampleTurnaround($r, $from, $to),
            'requisition-details' => $this->requisitionDetails($r, $from, $to),
            'requisition-summary' => $this->requisitionSummary($r, $from, $to),
        };
    }

    private function pos(Request $r)
    {
        return OrderPo::query()->with(['order.buyer', 'style', 'color'])
            ->whereHas('order', fn ($q) => $q->where('status', 'confirmed')->when($r->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $r->buyer_id)));
    }

    /** po_id => [stage => pass] for the given POs. */
    private function passByStage($poIds): array
    {
        $out = [];
        foreach (Entry::query()->whereIn('order_po_id', $poIds)->selectRaw('order_po_id, stage, SUM(' . ProductionFlow::NET_PASS_SQL . ') p')->groupBy('order_po_id', 'stage')->get() as $row) {
            $out[$row->order_po_id][$row->stage] = (int) $row->p;
        }

        return $out;
    }

    private function fgReceived($poIds): array
    {
        if (! class_exists(\ME\SflInventory\Models\InvFinishedGoodsReceive::class)) {
            return [];
        }

        return DB::table('inv_finished_goods_receives as r')->join('inv_finished_goods_receive_items as ri', 'ri.fg_receive_id', '=', 'r.id')
            ->whereIn('r.msfl_order_po_id', $poIds)->groupBy('r.msfl_order_po_id')
            ->selectRaw('r.msfl_order_po_id po, SUM(ri.quantity) q')->pluck('q', 'po')->map(fn ($q) => (int) $q)->all();
    }

    private function orderBook(Request $r, $from, $to): array
    {
        $pos = $this->pos($r)->when($from, fn ($q) => $q->where('shipment_date', '>=', $from))->when($to, fn ($q) => $q->where('shipment_date', '<=', $to))
            ->orderBy('shipment_date')->get();
        $pass = $this->passByStage($pos->pluck('id'));
        $fg = $this->fgReceived($pos->pluck('id'));

        $rows = $pos->map(fn ($po) => [
            $po->order->order_no . ' · ' . $po->po_no, $po->order->buyer->name ?? '', $po->style->style_no ?? '', $po->color->name ?? '',
            $po->po_qty, number_format((float) $po->unit_price, 2), (float) $po->total_value,
            $po->pcd_date?->format('d-M-y') ?? '', $po->shipment_date?->format('d-M-y') ?? '',
            $packed = $pass[$po->id]['packing'] ?? 0, $fg[$po->id] ?? 0, max(0, $po->po_qty - $packed),
        ]);

        return [
            'headers' => ['Order · PO', 'Buyer', 'Style', 'Color', 'Qty', 'FOB', 'Value', 'PCD', 'Shipment', 'Packed', 'Finish Store', 'Balance'],
            'align' => [4 => 'right', 5 => 'right', 6 => 'right', 9 => 'right', 10 => 'right', 11 => 'right'],
            'rows' => $rows->all(),
            'totals' => [4 => $rows->sum(4), 6 => $rows->sum(6), 9 => $rows->sum(9), 10 => $rows->sum(10), 11 => $rows->sum(11)],
        ];
    }

    private function dailyProduction(Request $r, $from, $to): array
    {
        $entries = Entry::query()->with(['orderPo.order.buyer', 'orderPo.style', 'size', 'line.floorLine'])
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($r->filled('stage'), fn ($q) => $q->where('stage', $r->stage))
            ->when($r->filled('line_id'), fn ($q) => $q->where('line_id', $r->line_id))
            ->when($r->filled('buyer_id'), fn ($q) => $q->whereHas('orderPo.order', fn ($q) => $q->where('buyer_id', $r->buyer_id)))
            ->orderBy('entry_date')->orderBy('stage')->get();

        $rows = $entries->map(fn ($e) => [
            $e->entry_date->format('d-M-y'), ProductionFlow::label($e->stage) . ($e->kind !== 'production' ? ' — ' . ProductionFlow::KINDS[$e->kind] : ''), $e->line->name ?? '',
            ($e->orderPo->order->order_no ?? '') . ' · ' . ($e->orderPo->po_no ?? ''), $e->orderPo->order->buyer->name ?? '', $e->orderPo->style->style_no ?? '',
            $e->size->name ?? 'All', $e->input_qty, $e->pass_qty, $e->rework_qty, $e->reject_qty,
            ($e->pass_qty + $e->reject_qty) > 0 ? round($e->reject_qty / ($e->pass_qty + $e->reject_qty) * 100, 1) . '%' : '',
        ]);

        return [
            'headers' => ['Date', 'Stage', 'Line', 'Order · PO', 'Buyer', 'Style', 'Size', 'Input', 'Pass', 'Rework', 'Reject', 'Reject %'],
            'align' => [7 => 'right', 8 => 'right', 9 => 'right', 10 => 'right', 11 => 'right'],
            'rows' => $rows->all(),
            'totals' => [7 => $rows->sum(7), 8 => $rows->sum(8), 9 => $rows->sum(9), 10 => $rows->sum(10)],
            'period' => $from->format('d-M-Y') . ' – ' . $to->format('d-M-Y'),
        ];
    }

    private function defects(Request $r, $from, $to): array
    {
        $rows = DB::table('msfl_prod_entry_defects as d')->join('msfl_prod_entries as e', 'e.id', '=', 'd.entry_id')
            ->leftJoin('inv_machines as m', 'm.id', '=', 'd.machine_id')
            ->whereNull('e.deleted_at')->whereBetween('e.entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($r->filled('stage'), fn ($q) => $q->where('e.stage', $r->stage))
            ->when($r->filled('line_id'), fn ($q) => $q->where('e.line_id', $r->line_id))
            ->groupBy('e.stage', 'd.part_name', 'm.code', 'm.name', 'd.defect')
            ->selectRaw("e.stage, d.part_name, CONCAT_WS(' — ', m.code, m.name) machine, d.defect,
                SUM(CASE WHEN d.type = 'reject' THEN d.qty ELSE 0 END) rj, SUM(CASE WHEN d.type = 'rework' THEN d.qty ELSE 0 END) rw")
            ->orderByRaw('SUM(d.qty) DESC')->get();
        $checked = Entry::query()->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('stage, SUM(pass_qty + reject_qty) c')->groupBy('stage')->pluck('c', 'stage');
        // Cutting has no production entries: what it checked is what it cut.
        $checked['cutting'] = (int) \ME\MerchandisingSfl\Models\Production\Cutting::query()
            ->whereBetween('cutting_date', [$from->toDateString(), $to->toDateString()])->sum('total_qty');

        $data = $rows->map(fn ($x) => [
            ProductionFlow::label($x->stage), $x->part_name ?: '—', $x->machine ?: '—', $x->defect ?: '—', (int) $x->rj, (int) $x->rw,
            ($checked[$x->stage] ?? 0) > 0 ? round(($x->rj + $x->rw) / $checked[$x->stage] * 100, 2) . '%' : '',
        ]);

        return [
            'headers' => ['Stage', 'Part', 'Machine', 'Defect', 'Reject', 'Rework', '% of checked'],
            'align' => [4 => 'right', 5 => 'right', 6 => 'right'],
            'rows' => $data->all(),
            'totals' => [4 => $data->sum(4), 5 => $data->sum(5)],
            'period' => $from->format('d-M-Y') . ' – ' . $to->format('d-M-Y'),
        ];
    }

    private function shipmentStatus(Request $r, $from, $to): array
    {
        $pos = $this->pos($r)->whereBetween('shipment_date', [$from->toDateString(), $to->toDateString()])->orderBy('shipment_date')->get();
        $pass = $this->passByStage($pos->pluck('id'));
        $cut = \ME\MerchandisingSfl\Models\Production\Cutting::query()->whereIn('order_po_id', $pos->pluck('id'))->groupBy('order_po_id')
            ->selectRaw('order_po_id, SUM(total_qty) q')->pluck('q', 'order_po_id');
        $fg = $this->fgReceived($pos->pluck('id'));

        $rows = $pos->map(function ($po) use ($pass, $cut, $fg) {
            $packed = $pass[$po->id]['packing'] ?? 0;
            $days = (int) today()->diffInDays($po->shipment_date, false);
            $pct = $po->po_qty > 0 ? $packed / $po->po_qty * 100 : 0;
            $status = $pct >= 100 ? 'Packed' : ($days < 0 ? 'Late' : ($days <= 7 && $pct < 80 ? 'At risk' : 'On track'));

            return [
                $po->shipment_date->format('d-M-y'), $days >= 0 ? $days . ' d' : abs($days) . ' d late',
                $po->order->order_no . ' · ' . $po->po_no, $po->order->buyer->name ?? '', $po->style->style_no ?? '',
                $po->po_qty, (int) ($cut[$po->id] ?? 0), $pass[$po->id]['sewing'] ?? 0, $packed, $fg[$po->id] ?? 0, round($pct) . '%', $status,
            ];
        });

        return [
            'headers' => ['Shipment', 'Days', 'Order · PO', 'Buyer', 'Style', 'Qty', 'Cut', 'Sewn', 'Packed', 'Finish Store', 'Packed %', 'Status'],
            'align' => [5 => 'right', 6 => 'right', 7 => 'right', 8 => 'right', 9 => 'right', 10 => 'right'],
            'rows' => $rows->all(),
            'totals' => [5 => $rows->sum(5), 6 => $rows->sum(6), 7 => $rows->sum(7), 8 => $rows->sum(8), 9 => $rows->sum(9)],
            'period' => $from->format('d-M-Y') . ' – ' . $to->format('d-M-Y'),
            'status_col' => 11,
        ];
    }

    private function tnaStatus(Request $r): array
    {
        $tasks = TnaTask::query()->with(['plan.order.buyer', 'plan.style', 'responsible'])
            ->whereNull('actual_date')->where('is_na', false)
            ->whereHas('plan', fn ($q) => $q->where('status', 'active')
                ->when($r->filled('buyer_id'), fn ($q) => $q->whereHas('order', fn ($q) => $q->where('buyer_id', $r->buyer_id))))
            ->whereRaw('COALESCE(revised_date, plan_date) <= ?', [today()->addDays(7)->toDateString()])
            ->orderByRaw('COALESCE(revised_date, plan_date)')->get();

        $rows = $tasks->map(function ($t) {
            $due = $t->revised_date ?? $t->plan_date;
            $days = (int) today()->diffInDays($due, false);

            return [
                $t->plan->tna_no ?? '', $t->plan->order->buyer->name ?? '', $t->plan->style->style_no ?? '', $t->group_name, $t->task_name,
                $due->format('d-M-y'), $days < 0 ? abs($days) . ' d overdue' : ($days === 0 ? 'today' : 'in ' . $days . ' d'),
                $t->is_mandatory ? 'Yes' : '', $t->responsible->name ?? '', $days < 0 ? 'Late' : 'At risk',
            ];
        });

        return [
            'headers' => ['T&A', 'Buyer', 'Style', 'Group', 'Task', 'Due', 'When', 'Mandatory', 'Responsible', 'Status'],
            'align' => [], 'rows' => $rows->all(), 'totals' => null, 'status_col' => 9,
        ];
    }

    private function sampleTurnaround(Request $r, $from, $to): array
    {
        $samples = Sample::query()->with(['style', 'buyer', 'sampleType'])
            ->when($r->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $r->buyer_id))
            ->when($from, fn ($q) => $q->where('request_date', '>=', $from))->when($to, fn ($q) => $q->where('request_date', '<=', $to))
            ->orderByDesc('request_date')->get();

        $rows = $samples->map(fn ($s) => [
            $s->sample_no, $s->buyer->name ?? '', $s->style->style_no ?? '', $s->sampleType->name ?? '', $s->revision_no ?: '',
            $s->request_date?->format('d-M-y') ?? '', $s->submit_date?->format('d-M-y') ?? '', $s->decision_date?->format('d-M-y') ?? '',
            $s->submit_date ? $s->request_date->diffInDays($s->submit_date) : '', $s->decision_date && $s->submit_date ? $s->submit_date->diffInDays($s->decision_date) : '',
            ucfirst(str_replace('_', ' ', $s->status)),
        ]);

        return [
            'headers' => ['Sample', 'Buyer', 'Style', 'Type', 'Rev', 'Requested', 'Submitted', 'Decided', 'Days to submit', 'Buyer days', 'Status'],
            'align' => [8 => 'right', 9 => 'right'], 'rows' => $rows->all(), 'totals' => null, 'status_col' => 10,
        ];
    }

    /**
     * Requisition items raised from Production → Fabric Requisition (Inventory requisitions),
     * with buyer / style / PO / item. Filters: buyer, style, requisition date.
     */
    private function requisitionItems(Request $r, $from, $to)
    {
        return DB::table('msfl_prod_fabric_requisitions as fr')
            ->join('inv_requisitions as rq', 'rq.id', '=', 'fr.inv_requisition_id')
            ->join('inv_requisition_items as ri', 'ri.requisition_id', '=', 'rq.id')
            ->join('msfl_order_pos as po', 'po.id', '=', 'fr.order_po_id')
            ->join('msfl_orders as o', 'o.id', '=', 'po.order_id')
            ->join('msfl_buyers as b', 'b.id', '=', 'o.buyer_id')
            ->join('msfl_styles as st', 'st.id', '=', 'po.style_id')
            ->leftJoin('inv_colors as c', 'c.id', '=', 'po.color_id')
            ->leftJoin('inv_items as it', 'it.id', '=', 'ri.item_id')
            ->leftJoin('inv_units as u', 'u.id', '=', 'it.unit_id')
            ->whereNull('rq.deleted_at')
            ->when($r->filled('buyer_id'), fn ($q) => $q->where('o.buyer_id', $r->buyer_id))
            ->when($r->filled('style_id'), fn ($q) => $q->where('po.style_id', $r->style_id))
            ->when($from, fn ($q) => $q->whereDate('rq.requisition_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('rq.requisition_date', '<=', $to->toDateString()));
    }

    /** requisition_item_id => issue lines [date, issue no, qty] of issues not cancelled. */
    private function issueLines($itemIds)
    {
        return DB::table('inv_issue_items as ii')->join('inv_issues as i', 'i.id', '=', 'ii.issue_id')
            ->whereIn('ii.requisition_item_id', $itemIds)->whereNull('i.deleted_at')->where('i.status', '!=', 'cancelled')
            ->orderBy('i.issue_date')->orderBy('i.id')
            ->get(['ii.requisition_item_id', 'i.issue_date', 'i.issue_no', 'ii.issued_qty'])->groupBy('requisition_item_id');
    }

    private function requisitionDetails(Request $r, $from, $to): array
    {
        $items = $this->requisitionItems($r, $from, $to)
            ->orderByDesc('rq.requisition_date')->orderByDesc('rq.id')
            ->get(['ri.id', 'rq.requisition_no', 'rq.requisition_date', 'rq.status', 'b.name as buyer', 'st.style_no', 'po.po_no', 'o.order_no',
                'c.name as color', 'it.item_code', 'it.item_name', 'u.short_name as unit', 'ri.requested_qty', 'ri.approved_qty', 'ri.issued_qty']);
        $issues = $this->issueLines($items->pluck('id'));
        $q = fn ($v) => round((float) $v, 2);

        $rows = $items->map(fn ($x) => [
            Carbon::parse($x->requisition_date)->format('d-M-y'), $x->requisition_no, $x->buyer, $x->style_no, $x->order_no . ' · ' . $x->po_no . ' · ' . $x->color,
            trim($x->item_code . ' ' . $x->item_name), $x->unit, $q($x->requested_qty), $q($x->approved_qty), $q($x->issued_qty),
            $q(max(0, (float) ($x->approved_qty ?? $x->requested_qty) - (float) $x->issued_qty)),
            ($issues[$x->id] ?? collect())->map(fn ($i) => Carbon::parse($i->issue_date)->format('d-M-y') . ': ' . $q($i->issued_qty) . ' (' . $i->issue_no . ')')->implode(', ') ?: '—',
            ucfirst(str_replace('_', ' ', $x->status)),
        ]);

        return [
            'headers' => ['Req. Date', 'Requisition', 'Buyer', 'Style', 'Order · PO · Color', 'Item', 'Unit', 'Requested', 'Approved', 'Issued', 'To Issue', 'Issued on (date: qty)', 'Status'],
            'align' => [7 => 'right', 8 => 'right', 9 => 'right', 10 => 'right'],
            'rows' => $rows->all(),
            'totals' => [7 => $rows->sum(7), 8 => $rows->sum(8), 9 => $rows->sum(9), 10 => $rows->sum(10)],
            'period' => $from || $to ? ($from?->format('d-M-Y') ?? '…') . ' – ' . ($to?->format('d-M-Y') ?? '…') : null,
        ];
    }

    private function requisitionSummary(Request $r, $from, $to): array
    {
        $items = $this->requisitionItems($r, $from, $to)
            ->get(['ri.id', 'rq.id as req_id', 'b.name as buyer', 'st.style_no', 'it.item_code', 'it.item_name', 'u.short_name as unit',
                'ri.requested_qty', 'ri.approved_qty', 'ri.issued_qty']);
        $issues = $this->issueLines($items->pluck('id'));
        $q = fn ($v) => round((float) $v, 2);

        $rows = $items->groupBy(fn ($x) => $x->buyer . '|' . $x->style_no . '|' . $x->item_code)
            ->map(function ($g) use ($issues, $q) {
                $x = $g->first();
                $dates = $g->flatMap(fn ($i) => $issues[$i->id] ?? [])->pluck('issue_date')->sort()->values();
                $approved = $g->sum(fn ($i) => (float) ($i->approved_qty ?? $i->requested_qty));

                return [
                    $x->buyer, $x->style_no, trim($x->item_code . ' ' . $x->item_name), $x->unit, $g->pluck('req_id')->unique()->count(),
                    $q($g->sum('requested_qty')), $q($approved), $q($g->sum('issued_qty')), $q(max(0, $approved - $g->sum('issued_qty'))),
                    $dates->isEmpty() ? '—' : Carbon::parse($dates->first())->format('d-M-y'),
                    $dates->isEmpty() ? '—' : Carbon::parse($dates->last())->format('d-M-y'),
                ];
            })->sortBy(fn ($row) => $row[0] . $row[1] . $row[2])->values();

        return [
            'headers' => ['Buyer', 'Style', 'Item', 'Unit', 'Requisitions', 'Requested', 'Approved', 'Issued', 'To Issue', 'First Issue', 'Last Issue'],
            'align' => [4 => 'right', 5 => 'right', 6 => 'right', 7 => 'right', 8 => 'right'],
            'rows' => $rows->all(),
            'totals' => [4 => $rows->sum(4), 5 => $rows->sum(5), 6 => $rows->sum(6), 7 => $rows->sum(7), 8 => $rows->sum(8)],
            'period' => $from || $to ? ($from?->format('d-M-Y') ?? '…') . ' – ' . ($to?->format('d-M-Y') ?? '…') : null,
        ];
    }
}

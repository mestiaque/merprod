<?php

namespace ME\MerchandisingSfl\Http\Controllers\Commercial;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Controllers\Controller;
use ME\MerchandisingSfl\Http\Requests\Commercial\InvoiceRequest;
use ME\MerchandisingSfl\Models\Commercial\ExportLc;
use ME\MerchandisingSfl\Models\Commercial\Invoice;
use ME\MerchandisingSfl\Services\Commercial\LcStatus;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Commercial → Commercial Invoice: what was shipped against an active Export LC
 * (qty per PO × the PO's FOB, capped by packed − already invoiced) + the shipping /
 * packing figures. Prints the Commercial Invoice and the Packing List.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly LcStatus $status)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_com_invoice.list');

        $invoices = Invoice::query()->with(['buyer', 'exportLc.currency', 'shipMode'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('invoice_no', 'like', '%' . $request->search . '%')
                ->orWhere('exp_no', 'like', '%' . $request->search . '%')->orWhere('bl_no', 'like', '%' . $request->search . '%')))
            ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id))
            ->when($request->filled('export_lc_id'), fn ($q) => $q->where('export_lc_id', $request->export_lc_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('invoice_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('invoice_date', '<=', $request->to))
            ->latest('invoice_date')->latest('id')->paginate($this->perPage(20))->withQueryString();

        return view('merchandising-sfl::admin.commercial.invoices.index', [
            'invoices' => $invoices, 'buyers' => Lookups::buyers(), 'lcs' => ExportLc::with('buyer')->latest('id')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_com_invoice.add');
        $lc = ExportLc::query()->where('status', 'active')->find($request->integer('export_lc_id'));

        return view('merchandising-sfl::admin.commercial.invoices.create', $this->formData(new Invoice(['invoice_date' => now()]), $lc, $request->integer('inv_shipment_id') ?: null));
    }

    public function store(InvoiceRequest $request, DocumentNumberService $numbers): RedirectResponse
    {
        $lc = ExportLc::findOrFail($request->export_lc_id);
        $invoice = DB::transaction(function () use ($request, $numbers, $lc) {
            $invoice = Invoice::create(Arr::except($request->validated(), ['lines']) + [
                'invoice_no' => $numbers->next('com_invoice', Invoice::class, 'invoice_no'), 'buyer_id' => $lc->buyer_id, 'created_by' => auth()->id(),
            ]);
            $this->saveLines($invoice, $request->validated('lines'));

            return $invoice;
        });

        return redirect()->route('msfl.commercial.invoices.show', $invoice)->with('success', "Commercial invoice {$invoice->invoice_no} saved." . $this->overLimit($lc));
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('msfl_com_invoice.view');
        $invoice->load(['buyer', 'exportLc.currency', 'shipMode', 'creator', 'lines.orderPo.style', 'lines.orderPo.color', 'lines.orderPo.order']);

        return view('merchandising-sfl::admin.commercial.invoices.show', ['invoice' => $invoice, 'shipmentNo' => $this->shipmentNo($invoice->inv_shipment_id)]);
    }

    /** Commercial Invoice or Packing List on printMaster2. */
    public function print(Invoice $invoice, string $doc): View
    {
        $this->authorize('msfl_com_invoice.view');
        $invoice->load(['buyer', 'exportLc.currency', 'exportLc.paymentTerm', 'exportLc.issuingBank', 'exportLc.lienBank', 'shipMode', 'lines.orderPo.style', 'lines.orderPo.color', 'lines.orderPo.order', 'lines.orderPo.sizes.size']);

        return view('merchandising-sfl::admin.commercial.invoices.print', ['invoice' => $invoice, 'doc' => $doc]);
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('msfl_com_invoice.edit');

        return view('merchandising-sfl::admin.commercial.invoices.edit', $this->formData($invoice->load('lines'), $invoice->exportLc, null));
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $lc = ExportLc::findOrFail($request->export_lc_id);
        DB::transaction(function () use ($request, $invoice, $lc) {
            $invoice->update(Arr::except($request->validated(), ['lines']) + ['buyer_id' => $lc->buyer_id]);
            $invoice->lines()->delete();
            $this->saveLines($invoice, $request->validated('lines'));
        });

        return redirect()->route('msfl.commercial.invoices.show', $invoice)->with('success', 'Commercial invoice updated.' . $this->overLimit($lc));
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('msfl_com_invoice.delete');
        $invoice->delete();

        return redirect()->route('msfl.commercial.invoices.index')->with('success', "Commercial invoice {$invoice->invoice_no} deleted.");
    }

    private function saveLines(Invoice $invoice, array $lines): void
    {
        $prices = \ME\MerchandisingSfl\Models\OrderPo::whereIn('id', array_column($lines, 'order_po_id'))->pluck('unit_price', 'id');
        foreach ($lines as $line) {
            $invoice->lines()->create(['order_po_id' => $line['order_po_id'], 'qty' => $line['qty'], 'cartons' => (int) ($line['cartons'] ?? 0), 'unit_price' => $prices[$line['order_po_id']] ?? 0]);
        }
        $invoice->refreshTotals();
    }

    /** Warning appended to the success message when the LC's shipped value passes LC value + tolerance. */
    private function overLimit(ExportLc $lc): string
    {
        $shipped = $this->status->shippedValue($lc);

        return $shipped > $lc->maxValue()
            ? ' Note: shipped value ' . number_format($shipped, 2) . ' is above LC value + tolerance (' . number_format($lc->maxValue(), 2) . ') — an LC amendment may be needed.'
            : '';
    }

    private function formData(Invoice $invoice, ?ExportLc $lc, ?int $shipmentId): array
    {
        $rows = $lc ? $this->status->poRows($lc, $invoice->exists ? $invoice->id : null) : collect();
        $shipments = $lc ? $this->shipments($rows->pluck('po.id')) : collect();
        $shipmentQty = $shipmentId ? ($shipments->firstWhere('id', $shipmentId)['qty'] ?? []) : [];
        $current = $invoice->exists ? $invoice->lines->keyBy('order_po_id') : collect();

        return [
            'invoice' => $invoice,
            'lc' => $lc,
            'lcs' => ExportLc::query()->with('buyer')->where('status', 'active')->orderByDesc('id')->get(),
            'rows' => $rows,
            'shipments' => $shipments,
            'shipmentId' => $shipmentId ?? $invoice->inv_shipment_id,
            // po_id => [qty, cartons] the form starts with: the invoice's own lines, else the picked shipment.
            'start' => $rows->mapWithKeys(fn ($r) => [$r['po']->id => [
                'qty' => old('lines') ? null : ($current[$r['po']->id]->qty ?? ($shipmentQty[$r['po']->id] ?? null)),
                'cartons' => $current[$r['po']->id]->cartons ?? null,
            ]]),
            'shipModes' => Lookups::shipModes(),
        ];
    }

    /** Inventory shipments with lines for these POs: [id, label, qty => [po_id => qty]]. */
    private function shipments(Collection $poIds): Collection
    {
        if (! Schema::hasColumn('inv_shipment_items', 'msfl_order_po_id')) {
            return collect();
        }

        return DB::table('inv_shipment_items as si')->join('inv_shipments as s', 's.id', '=', 'si.shipment_id')
            ->whereNull('s.deleted_at')->whereIn('si.msfl_order_po_id', $poIds)
            ->select('s.id', 's.shipment_no', 's.shipment_date', 's.invoice_no', 'si.msfl_order_po_id', DB::raw('SUM(si.quantity) q'))
            ->groupBy('s.id', 's.shipment_no', 's.shipment_date', 's.invoice_no', 'si.msfl_order_po_id')->orderByDesc('s.shipment_date')->get()
            ->groupBy('id')->map(fn ($g) => [
                'id' => $g->first()->id,
                'label' => $g->first()->shipment_no . ' · ' . $g->first()->shipment_date . ($g->first()->invoice_no ? ' · inv ' . $g->first()->invoice_no : '') . ' · ' . (int) $g->sum('q') . ' pcs',
                'qty' => $g->mapWithKeys(fn ($r) => [$r->msfl_order_po_id => (int) $r->q])->all(),
            ])->values();
    }

    private function shipmentNo(?int $id): ?string
    {
        return $id && Schema::hasTable('inv_shipments') ? DB::table('inv_shipments')->where('id', $id)->value('shipment_no') : null;
    }
}

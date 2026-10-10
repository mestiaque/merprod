@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Invoice ' . $invoice->invoice_no) }}</title>
@endsection

@php $cur = $invoice->exportLc->currency->code ?? ''; @endphp

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h4 class="mb-0">Commercial Invoice — {{ $invoice->invoice_no }}</h4>
            <div>
                <a href="{{ route('msfl.commercial.invoices.print', [$invoice, 'invoice']) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-print"></i> Commercial Invoice</a>
                <a href="{{ route('msfl.commercial.invoices.print', [$invoice, 'packing']) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-box"></i> Packing List</a>
                @can('msfl_com_invoice.edit')<a href="{{ route('msfl.commercial.invoices.edit', $invoice) }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>@endcan
                <a href="{{ route('msfl.commercial.invoices.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col-md-3 mb-2"><strong>Date:</strong> {{ $invoice->invoice_date->format('d M Y') }}</div>
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $invoice->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>LC / SC:</strong> <a href="{{ route('msfl.commercial.export-lcs.show', $invoice->exportLc) }}">{{ $invoice->exportLc->lc_no }}</a> <small class="text-muted">{{ $invoice->exportLc->buyer_lc_no }}</small></div>
                <div class="col-md-3 mb-2"><strong>Inventory Shipment:</strong> {{ $shipmentNo ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>EXP:</strong> {{ $invoice->exp_no ?? '-' }} {{ $invoice->exp_date ? '(' . $invoice->exp_date->format('d M Y') . ')' : '' }}</div>
                <div class="col-md-3 mb-2"><strong>B/L / AWB:</strong> {{ $invoice->bl_no ?? '-' }} {{ $invoice->bl_date ? '(' . $invoice->bl_date->format('d M Y') . ')' : '' }}</div>
                <div class="col-md-3 mb-2"><strong>Ship Mode:</strong> {{ $invoice->shipMode->name ?? '-' }} · {{ $invoice->vessel ?? '' }}</div>
                <div class="col-md-3 mb-2"><strong>Container:</strong> {{ $invoice->container_no ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>From → To:</strong> {{ $invoice->port_of_loading ?? '-' }} → {{ $invoice->port_of_discharge ?? '-' }} {{ $invoice->final_destination ? '(' . $invoice->final_destination . ')' : '' }}</div>
                <div class="col-md-3 mb-2"><strong>Weight:</strong> N {{ $invoice->net_weight ?? '-' }} / G {{ $invoice->gross_weight ?? '-' }} kg · CBM {{ $invoice->cbm ?? '-' }}</div>
                @if($invoice->remarks)<div class="col-12 mb-2"><strong>Remarks:</strong> {!! nl2br(e($invoice->remarks)) !!}</div>@endif
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th>Order</th><th>PO No</th><th>Style</th><th>Color</th><th class="text-right">Qty (pcs)</th><th class="text-right">Cartons</th><th class="text-right">FOB</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @foreach($invoice->lines as $line)
                            <tr>
                                <td>{{ $line->orderPo->order->order_no ?? '' }}</td>
                                <td>{{ $line->orderPo->po_no ?? '' }}</td>
                                <td>{{ $line->orderPo->style->style_no ?? '-' }}</td>
                                <td>{{ $line->orderPo->color->name ?? '-' }}</td>
                                <td class="text-right">{{ number_format($line->qty) }}</td>
                                <td class="text-right">{{ number_format($line->cartons) }}</td>
                                <td class="text-right">{{ number_format((float) $line->unit_price, 4) }}</td>
                                <td class="text-right">{{ number_format((float) $line->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="font-weight-bold"><td colspan="4" class="text-right">Total</td><td class="text-right">{{ number_format($invoice->total_qty) }}</td><td class="text-right">{{ number_format($invoice->total_cartons) }}</td><td></td><td class="text-right">{{ $cur }} {{ number_format((float) $invoice->total_value, 2) }}</td></tr></tfoot>
                </table>
            </div>
            <p class="small mb-0"><strong>In words:</strong> {{ $invoice->amountInWords() }}</p>
        </div>
    </div>
</div>
@endsection

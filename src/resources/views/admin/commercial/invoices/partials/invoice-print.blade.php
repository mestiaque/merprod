{{--
    Commercial Invoice print — plain black & white layout.
    props: invoice (with exportLc, buyer, shipMode, lines.orderPo.style/color loaded)

    NOT YET STORED — printed blank on purpose until the data exists:
    Consignee (separate from buyer), Exchange Rate, Incoterm, HS Code, per-line
    Net / Gross Weight and Remarks, Discount, Freight / Other, Advance Received,
    Balance Due. Unit is always PCS; Country of Origin is the factory's (Bangladesh).
--}}
@php
    $lc = $invoice->exportLc;
    $cur = $lc->currency->code ?? '';
    $buyer = $invoice->buyer;
    $d = fn ($date) => $date ? $date->format('d-M-Y') : '';
@endphp

<style>
    .inv { color: #000; font-size: 10.5px; }
    .inv table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .inv th, .inv td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; color: #000; background: #fff !important; }
    .inv .lbl { font-weight: 700; white-space: nowrap; width: 1%; }
    .inv .sec { font-weight: 700; text-transform: uppercase; letter-spacing: .3px; font-size: 10px; }
    .inv .lines th { font-weight: 700; text-align: center; vertical-align: middle; border-bottom: 2px solid #000; }
    .inv .lines td { vertical-align: middle; }
    .inv .r { text-align: right; } .inv .c { text-align: center; }
    .inv .lines tfoot td { font-weight: 700; border-top: 2px solid #000; }
    .inv .bottom { display: flex; gap: 12px; align-items: flex-start; }
    .inv .bottom .words { flex: 1; }
    .inv .totals { width: 260px !important; flex: 0 0 260px; }
    .inv .totals .grand td { font-weight: 700; border-top: 2px solid #000; }
    .inv .box { border: 1px solid #000; padding: 5px 6px; min-height: 48px; margin-bottom: 8px; }
    .inv .sign { display: flex; justify-content: space-between; margin-top: 46px; }
    .inv .sign div { width: 28%; border-top: 1px solid #000; padding-top: 3px; text-align: center; font-weight: 700; }
</style>

<div class="inv">
    {{-- Parties --}}
    <table>
        <tr>
            <td style="width:50%"><div class="sec">Buyer / Bill To</div>
                <strong>{{ $buyer->name ?? '' }}</strong>
                @if($buyer?->address)<br>{!! nl2br(e($buyer->address)) !!}@endif
                @if($buyer?->country)<br>{{ $buyer->country }}@endif
            </td>
            <td style="width:50%"><div class="sec">Consignee</div>&nbsp;</td>
        </tr>
    </table>

    {{-- Invoice / shipment details --}}
    <table>
        <tr>
            <td class="lbl">Invoice No</td><td>{{ $invoice->invoice_no }}</td>
            <td class="lbl">Invoice Date</td><td>{{ $d($invoice->invoice_date) }}</td>
            <td class="lbl">{{ $lc->type === 'sc' ? 'Contract' : 'LC' }} No / Date</td><td>{{ $lc->buyer_lc_no }}{{ $lc->lc_date ? ' · ' . $d($lc->lc_date) : '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Currency</td><td>{{ $cur }}</td>
            <td class="lbl">Exchange Rate</td><td></td>
            <td class="lbl">Payment Terms</td><td>{{ $lc->paymentTermLabel() }}</td>
        </tr>
        <tr>
            <td class="lbl">Country of Origin</td><td>Bangladesh</td>
            <td class="lbl">Destination</td><td>{{ $invoice->final_destination }}</td>
            <td class="lbl">Incoterm</td><td></td>
        </tr>
        <tr>
            <td class="lbl">Port of Loading</td><td>{{ $invoice->port_of_loading }}</td>
            <td class="lbl">Port of Discharge</td><td>{{ $invoice->port_of_discharge }}</td>
            <td class="lbl">Shipment Mode</td><td>{{ $invoice->shipMode->name ?? '' }}</td>
        </tr>
        <tr>
            <td class="lbl">Vessel / Flight</td><td>{{ $invoice->vessel }}</td>
            <td class="lbl">Container / B/L / AWB</td><td>{{ collect([$invoice->container_no, $invoice->bl_no])->filter()->implode(' / ') }}</td>
            <td class="lbl">Shipment Date</td><td>{{ $d($invoice->bl_date) }}</td>
        </tr>
        <tr>
            <td class="lbl">EXP No / Date</td><td colspan="5">{{ $invoice->exp_no }}{{ $invoice->exp_date ? ' · ' . $d($invoice->exp_date) : '' }}</td>
        </tr>
    </table>

    {{-- Line items --}}
    <table class="lines">
        <thead>
            <tr>
                <th>Sl.</th><th>Style</th><th>PO</th><th>HS Code</th><th>Description</th><th>Color / Size</th>
                <th>Cartons</th><th>Quantity</th><th>Unit</th><th>Unit Price@if($cur)<br>({{ $cur }})@endif</th><th>Amount@if($cur)<br>({{ $cur }})@endif</th>
                <th>Net Wt<br>(Kg)</th><th>Gross Wt<br>(Kg)</th><th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->lines as $line)
                @php $po = $line->orderPo; @endphp
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $po->style->style_no ?? '' }}</td>
                    <td>{{ $po->po_no }}</td>
                    <td></td>
                    <td>{{ $po->style->name ?? '' }}</td>
                    <td>{{ $po->color->name ?? '' }}</td>
                    <td class="r">{{ $line->cartons ? number_format($line->cartons) : '' }}</td>
                    <td class="r">{{ number_format($line->qty) }}</td>
                    <td class="c">PCS</td>
                    <td class="r">{{ number_format((float) $line->unit_price, 4) }}</td>
                    <td class="r">{{ number_format((float) $line->amount, 2) }}</td>
                    <td></td><td></td><td></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="r">Total</td>
                <td class="r">{{ $invoice->total_cartons ? number_format($invoice->total_cartons) : '' }}</td>
                <td class="r">{{ number_format($invoice->total_qty) }}</td>
                <td></td><td></td>
                <td class="r">{{ number_format((float) $invoice->total_value, 2) }}</td>
                <td class="r">{{ $invoice->net_weight }}</td>
                <td class="r">{{ $invoice->gross_weight }}</td>
                <td>{{ $invoice->cbm ? 'CBM ' . $invoice->cbm : '' }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Amount in words + totals --}}
    <div class="bottom">
        <div class="words box"><span class="sec">Amount in words:</span><br>{{ $invoice->amountInWords() }}</div>
        <table class="totals">
            <tr><td class="lbl">Subtotal</td><td class="r">{{ $cur }} {{ number_format((float) $invoice->total_value, 2) }}</td></tr>
            <tr><td class="lbl">Discount</td><td class="r"></td></tr>
            <tr><td class="lbl">Freight / Other</td><td class="r"></td></tr>
            <tr class="grand"><td class="lbl">Invoice Total</td><td class="r">{{ $cur }} {{ number_format((float) $invoice->total_value, 2) }}</td></tr>
            <tr><td class="lbl">Advance Received</td><td class="r"></td></tr>
            <tr class="grand"><td class="lbl">Balance Due</td><td class="r"></td></tr>
        </table>
    </div>

    {{-- Bank details / declaration --}}
    <div class="box">
        <span class="sec">Bank Details / Special Declaration:</span><br>
        @if($lc->issuingBank)Issuing Bank: {{ $lc->issuingBank->label() }}@if($lc->issuingBank->address), {{ $lc->issuingBank->address }}@endif<br>@endif
        @if($lc->lienBank)Our Bank: {{ $lc->lienBank->label() }}@if($lc->lienBank->account_no) · A/C {{ $lc->lienBank->account_no }}@endif<br>@endif
        @if($invoice->remarks){!! nl2br(e($invoice->remarks)) !!}@endif
    </div>

    <div class="sign"><div>Prepared By</div><div>Checked By</div><div>Authorized Signature</div></div>
</div>

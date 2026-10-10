{{--
    Commercial invoice: shipping / packing figures + qty per PO of the LC.
    props: invoice, lc (active ExportLc), rows (LcStatus::poRows, invoice itself left out), start (po_id => [qty, cartons]), shipmentId, shipModes
--}}
@php
    $cur = $lc->currency->code ?? '';
    $oldLines = collect(old('lines', []))->keyBy('order_po_id');
@endphp
<input type="hidden" name="export_lc_id" value="{{ $lc->id }}">
<input type="hidden" name="inv_shipment_id" value="{{ $shipmentId }}">
<div class="row">
    @include('merchandising-sfl::admin.partials.input', ['name' => 'invoice_date', 'label' => 'Invoice Date', 'type' => 'date', 'required' => true, 'value' => $invoice->invoice_date])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'exp_no', 'label' => 'EXP No', 'value' => $invoice->exp_no])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'exp_date', 'label' => 'EXP Date', 'type' => 'date', 'value' => $invoice->exp_date])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'ship_mode_id', 'label' => 'Ship Mode', 'options' => $shipModes->pluck('name', 'id'), 'value' => $invoice->ship_mode_id])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'bl_no', 'label' => 'B/L / AWB No', 'value' => $invoice->bl_no])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'bl_date', 'label' => 'B/L / AWB Date', 'type' => 'date', 'value' => $invoice->bl_date])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'vessel', 'label' => 'Vessel / Flight', 'value' => $invoice->vessel])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'container_no', 'label' => 'Container No', 'value' => $invoice->container_no])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'port_of_loading', 'label' => 'Port of Loading', 'value' => $invoice->port_of_loading ?? 'Chattogram, Bangladesh'])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'port_of_discharge', 'label' => 'Port of Discharge', 'value' => $invoice->port_of_discharge])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'final_destination', 'label' => 'Final Destination', 'value' => $invoice->final_destination])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'net_weight', 'label' => 'Net Weight (kg)', 'type' => 'number', 'value' => $invoice->net_weight, 'attrs' => 'step=any'])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'gross_weight', 'label' => 'Gross Weight (kg)', 'type' => 'number', 'value' => $invoice->gross_weight, 'attrs' => 'step=any'])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'cbm', 'label' => 'CBM', 'type' => 'number', 'value' => $invoice->cbm, 'attrs' => 'step=any'])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'col' => 6, 'value' => $invoice->remarks])
</div>

<h6 class="mt-2">Shipped qty per PO <small class="text-muted">— up to "Can Invoice" (packed − already invoiced); leave 0 for a PO not on this shipment</small></h6>
@error('lines')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
<div class="table-responsive">
    <table class="table table-bordered table-sm align-middle">
        <thead><tr><th>PO No</th><th>Style</th><th>Color</th><th class="text-right">Order Qty</th><th class="text-right">Packed</th><th class="text-right">Invoiced</th><th class="text-right">Can Invoice</th><th style="width:120px">Qty <span class="text-danger">*</span></th><th style="width:100px">Cartons</th><th class="text-right">FOB</th><th class="text-right">Amount</th></tr></thead>
        <tbody id="ciLines">
            @forelse($rows as $i => $r)
                @php
                    $po = $r['po'];
                    $old = $oldLines->get($po->id);
                    $qty = $old['qty'] ?? ($start[$po->id]['qty'] ?? '');
                    $cartons = $old['cartons'] ?? ($start[$po->id]['cartons'] ?? '');
                @endphp
                <tr data-price="{{ (float) $po->unit_price }}">
                    <td>{{ $po->po_no }}<input type="hidden" name="lines[{{ $i }}][order_po_id]" value="{{ $po->id }}"></td>
                    <td>{{ $po->style->style_no ?? '-' }}</td>
                    <td>{{ $po->color->name ?? '-' }}</td>
                    <td class="text-right">{{ number_format($r['qty']) }}</td>
                    <td class="text-right">{{ number_format($r['packed']) }}</td>
                    <td class="text-right">{{ number_format($r['invoiced']) }}</td>
                    <td class="text-right font-weight-bold">{{ number_format($r['available']) }}</td>
                    <td><input type="number" min="0" max="{{ $r['available'] }}" step="1" name="lines[{{ $i }}][qty]" class="form-control form-control-sm text-right{{ $errors->has("lines.$i.qty") ? ' is-invalid' : '' }}" value="{{ $qty }}" data-ci-qty></td>
                    <td><input type="number" min="0" step="1" name="lines[{{ $i }}][cartons]" class="form-control form-control-sm text-right" value="{{ $cartons }}" data-ci-cartons></td>
                    <td class="text-right">{{ number_format((float) $po->unit_price, 4) }}</td>
                    <td class="text-right" data-ci-amount>0.00</td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center text-muted">This LC has no PO.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr class="font-weight-bold"><td colspan="7" class="text-right">Total</td><td class="text-right" data-ci-total-qty>0</td><td class="text-right" data-ci-total-cartons>0</td><td></td><td class="text-right">{{ $cur }} <span data-ci-total>0.00</span></td></tr></tfoot>
    </table>
</div>

@push('js')
<script>
(function () {
    const body = document.getElementById('ciLines');
    function recalc() {
        let qty = 0, cartons = 0, total = 0;
        body.querySelectorAll('tr[data-price]').forEach(function (tr) {
            const q = parseInt(tr.querySelector('[data-ci-qty]').value) || 0;
            const amount = q * parseFloat(tr.dataset.price);
            tr.querySelector('[data-ci-amount]').textContent = amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            qty += q; cartons += parseInt(tr.querySelector('[data-ci-cartons]').value) || 0; total += amount;
        });
        document.querySelector('[data-ci-total-qty]').textContent = qty.toLocaleString();
        document.querySelector('[data-ci-total-cartons]').textContent = cartons.toLocaleString();
        document.querySelector('[data-ci-total]').textContent = total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    body.addEventListener('input', recalc);
    recalc();
})();
</script>
@endpush

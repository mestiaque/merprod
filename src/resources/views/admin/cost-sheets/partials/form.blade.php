{{-- props: costSheet (model, may be unsaved), buyers, styles, inquiries, currencies, itemOptions, uoms --}}
@php
    $lines = collect(old('items', $costSheet->relationLoaded('items') ? $costSheet->items->toArray() : []));
@endphp
<div class="row">
    @include('merchandising-sfl::admin.partials.select', ['name' => 'buyer_id', 'label' => 'Buyer', 'required' => true, 'options' => $buyers->pluck('name', 'id'), 'value' => $costSheet->buyer_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'style_id', 'label' => 'Style (if created)', 'options' => $styles->mapWithKeys(fn ($s) => [$s->id => $s->label()]), 'value' => $costSheet->style_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'inquiry_id', 'label' => 'Inquiry', 'options' => $inquiries->mapWithKeys(fn ($i) => [$i->id => $i->inquiry_no . ($i->style_ref ? ' — ' . $i->style_ref : '')]), 'value' => $costSheet->inquiry_id])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'costing_date', 'label' => 'Costing Date', 'type' => 'date', 'required' => true, 'value' => $costSheet->costing_date])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'style_ref', 'label' => 'Style Ref', 'value' => $costSheet->style_ref])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'garment_description', 'label' => 'Garment Description', 'value' => $costSheet->garment_description])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'size_range', 'label' => 'Size Range', 'value' => $costSheet->size_range])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'currency_id', 'label' => 'Currency', 'options' => $currencies->pluck('code', 'id'), 'value' => $costSheet->currency_id])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'order_qty', 'label' => 'Order Qty (pcs)', 'type' => 'number', 'value' => $costSheet->order_qty])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'smv', 'label' => 'SMV', 'type' => 'number', 'value' => $costSheet->smv])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'buyer_target_price', 'label' => 'Buyer Target Price / pc', 'type' => 'number', 'value' => $costSheet->buyer_target_price])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'final_price', 'label' => 'Final (Agreed) Price / pc', 'type' => 'number', 'value' => $costSheet->final_price])
</div>

@foreach(\ME\MerchandisingSfl\Models\CostSheet::GROUPS as $group => $groupLabel)
    <div class="d-flex justify-content-between align-items-center mb-2 mt-2">
        <h6 class="mb-0">{{ $groupLabel }} <small class="text-muted">(consumption &amp; amount per dozen)</small></h6>
        <button type="button" class="btn btn-sm btn-outline-primary" data-line-items-add="cs{{ $group }}"><i class="fa-solid fa-plus"></i> Add Line</button>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead><tr><th>Item</th><th>Description</th><th>Unit</th><th>Cons / dz</th><th>Rate</th><th>Amount / dz</th><th>Remarks</th><th></th></tr></thead>
            <tbody id="cs{{ $group }}RowsBody" data-cs-group="{{ $group }}">
                @php $groupLines = $lines->where('group', $group); @endphp
                @foreach($groupLines->isEmpty() ? [[]] : $groupLines as $index => $line)
                    @include('merchandising-sfl::admin.cost-sheets.partials.line-row', ['index' => $group . $index, 'group' => $group, 'line' => $line])
                @endforeach
            </tbody>
            <tfoot><tr><th colspan="5" class="text-right">{{ $groupLabel }} Total / dz</th><th class="text-right" data-cs-group-total="{{ $group }}">0.00</th><th colspan="2"></th></tr></tfoot>
        </table>
    </div>
    <template id="cs{{ $group }}RowTemplate">
        @include('merchandising-sfl::admin.cost-sheets.partials.line-row', ['index' => '__INDEX__', 'group' => $group, 'line' => []])
    </template>
@endforeach

<div class="row">
    <div class="col-md-6">
        <div class="row">
            @include('merchandising-sfl::admin.partials.input', ['name' => 'cm_cost', 'label' => 'CM / dz', 'type' => 'number', 'required' => true, 'col' => 6, 'value' => $costSheet->cm_cost, 'attrs' => 'data-cs-cm'])
            @include('merchandising-sfl::admin.partials.input', ['name' => 'commercial_percent', 'label' => 'Commercial % (on materials)', 'type' => 'number', 'required' => true, 'col' => 6, 'value' => $costSheet->commercial_percent, 'attrs' => 'data-cs-commercial'])
            @include('merchandising-sfl::admin.partials.input', ['name' => 'other_cost', 'label' => 'Other Cost / dz (test, freight …)', 'type' => 'number', 'required' => true, 'col' => 6, 'value' => $costSheet->other_cost, 'attrs' => 'data-cs-other'])
            @include('merchandising-sfl::admin.partials.input', ['name' => 'profit_percent', 'label' => 'Profit %', 'type' => 'number', 'required' => true, 'col' => 6, 'value' => $costSheet->profit_percent, 'attrs' => 'data-cs-profit'])
            @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'col' => 12, 'value' => $costSheet->remarks])
        </div>
    </div>
    <div class="col-md-6">
        <table class="table table-bordered table-sm">
            <tbody>
                <tr><th>Materials / dz (fabric + trims + process)</th><td class="text-right" data-cs-out="material">0.00</td></tr>
                <tr><th>CM / dz</th><td class="text-right" data-cs-out="cm">0.00</td></tr>
                <tr><th>Commercial / dz</th><td class="text-right" data-cs-out="commercial">0.00</td></tr>
                <tr><th>Other / dz</th><td class="text-right" data-cs-out="other">0.00</td></tr>
                <tr><th>Total Cost / dz</th><td class="text-right font-weight-bold" data-cs-out="total">0.00</td></tr>
                <tr><th>Profit / dz</th><td class="text-right" data-cs-out="profit">0.00</td></tr>
                <tr class="table-success"><th>Offer Price / pc</th><td class="text-right font-weight-bold" data-cs-out="offer">0.0000</td></tr>
            </tbody>
        </table>
    </div>
</div>

@include('merchandising-sfl::admin.partials.line-items-script')
@push('js')
<script>
    (function () {
        const num = (el) => parseFloat(el?.value) || 0;
        const out = (key, value, digits = 2) => { document.querySelector('[data-cs-out="' + key + '"]').textContent = value.toFixed(digits); };

        // Mirrors CostSheet::recalculate() — the server recomputes on save.
        function recalc() {
            let material = 0;
            document.querySelectorAll('[data-cs-group]').forEach(function (body) {
                let groupTotal = 0;
                body.querySelectorAll('[data-cs-row]').forEach(function (row) {
                    const amount = num(row.querySelector('[data-cs-cons]')) * num(row.querySelector('[data-cs-rate]'));
                    row.querySelector('[data-cs-amount]').value = amount ? amount.toFixed(4) : '';
                    groupTotal += amount;
                });
                document.querySelector('[data-cs-group-total="' + body.dataset.csGroup + '"]').textContent = groupTotal.toFixed(2);
                material += groupTotal;
            });
            const cm = num(document.querySelector('[data-cs-cm]'));
            const commercial = material * num(document.querySelector('[data-cs-commercial]')) / 100;
            const other = num(document.querySelector('[data-cs-other]'));
            const total = material + cm + commercial + other;
            const profit = total * num(document.querySelector('[data-cs-profit]')) / 100;
            out('material', material); out('cm', cm); out('commercial', commercial); out('other', other);
            out('total', total); out('profit', profit); out('offer', (total + profit) / 12, 4);
        }

        // Picking an item fills its default unit and rate.
        $(document).on('change', '[data-cs-item]', function () {
            const option = this.selectedOptions[0];
            const row = this.closest('tr');
            if (option && option.dataset.uom) row.querySelector('[data-cs-uom]').value = option.dataset.uom;
            if (option && option.dataset.rate && ! row.querySelector('[data-cs-rate]').value) row.querySelector('[data-cs-rate]').value = option.dataset.rate;
            recalc();
        });
        document.addEventListener('input', function (e) { if (e.target.closest('form')) recalc(); });
        document.addEventListener('msfl:rows-changed', recalc);
        recalc();
    })();
</script>
@endpush

{{-- Style picked → buyer, inquiry, style ref, description, SMV, qty, target price, sizes, currency; inquiry → the same from the inquiry. --}}
@include('merchandising-sfl::admin.partials.autofill', ['source' => 'style_id', 'map' => \ME\MerchandisingSfl\Support\Autofill::styles(), 'fields' => [
    'buyer_id' => 'buyer_id', 'inquiry_id' => 'inquiry_id', 'style_ref' => 'style_ref', 'garment_description' => 'garment_description', 'smv' => 'smv',
    'order_qty' => 'order_qty', 'buyer_target_price' => 'buyer_target_price', 'size_range' => 'size_ref', 'currency_id' => 'currency_id',
]])
@include('merchandising-sfl::admin.partials.autofill', ['source' => 'inquiry_id', 'map' => \ME\MerchandisingSfl\Support\Autofill::inquiries(), 'fields' => [
    'buyer_id' => 'buyer_id', 'style_ref' => 'style_ref', 'order_qty' => 'order_qty', 'buyer_target_price' => 'unit_price', 'smv' => 'smv',
]])

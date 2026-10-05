@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('New Cutting') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">New Cutting</h4>
            <a href="{{ route('msfl.production.cuttings.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.production.cuttings.store') }}">
                @csrf
                <div class="row">
                    @include('merchandising-sfl::admin.production.partials.po-select', ['selected' => $selectedPo])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'cutting_date', 'label' => 'Cutting Date', 'type' => 'date', 'required' => true, 'value' => now()])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'table_no', 'label' => 'Table No'])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'lay_count', 'label' => 'Lay / Ply Count', 'type' => 'number'])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'fabric_used', 'label' => 'Fabric Used', 'type' => 'number'])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'bundle_size', 'label' => 'Pcs per Bundle', 'type' => 'number', 'required' => true, 'value' => 20])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks'])
                </div>

                <h6>Cut Quantity per Size <small class="text-muted">— total: <span id="cutTotal">0</span> pcs</small></h6>
                <div id="sizeHint" class="small text-muted mb-2">Select a PO to see its order and already-cut quantity per size.</div>
                <div class="row">
                    @foreach($sizes as $size)
                        <div class="col-md-2 col-4 mb-2" data-size-box="{{ $size->id }}">
                            <label class="form-label small mb-0">{{ $size->name }} <small class="text-muted" data-size-info="{{ $size->id }}"></small></label>
                            <input type="number" min="0" step="1" name="sizes[{{ $size->id }}]" class="form-control form-control-sm" value="{{ old('sizes.' . $size->id) }}" data-cut-qty>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                    <h6 class="mb-0">Parts Cut <small class="text-muted">(e.g. Front, Back, Sleeve, Collar)</small></h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-line-items-add="part"><i class="fa-solid fa-plus"></i> Add Part</button>
                </div>
                <datalist id="partSuggestions">@foreach($partSuggestions as $p)<option value="{{ $p }}">@endforeach</datalist>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle" style="max-width:520px">
                        <thead><tr><th>Part</th><th style="width:140px">Pcs</th><th style="width:40px"></th></tr></thead>
                        <tbody id="partRowsBody">
                            @foreach(old('parts', [[]]) as $i => $part)
                                <tr>
                                    <td><input type="text" name="parts[{{ $i }}][part_name]" list="partSuggestions" class="form-control form-control-sm" value="{{ $part['part_name'] ?? '' }}"></td>
                                    <td><input type="number" min="0" step="1" name="parts[{{ $i }}][qty]" class="form-control form-control-sm text-right" value="{{ $part['qty'] ?? '' }}"></td>
                                    <td><button type="button" class="btn-custom danger" data-line-items-remove><i class="fa-solid fa-xmark"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <template id="partRowTemplate">
                    <tr>
                        <td><input type="text" name="parts[__INDEX__][part_name]" list="partSuggestions" class="form-control form-control-sm"></td>
                        <td><input type="number" min="0" step="1" name="parts[__INDEX__][qty]" class="form-control form-control-sm text-right"></td>
                        <td><button type="button" class="btn-custom danger" data-line-items-remove><i class="fa-solid fa-xmark"></i></button></td>
                    </tr>
                </template>

                <button type="submit" class="btn btn-primary mt-2 btn-sm">Save Cutting &amp; Make Bundles</button>
                <a href="{{ route('msfl.production.cuttings.index') }}" class="btn btn-light mt-2 btn-sm">Cancel</a>
            </form>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.line-items-script')
@include('merchandising-sfl::admin.partials.select2-init')
@endsection

@push('js')
<script>
(function () {
    // PO size order qty and what is already cut, so the cutter sees the balance.
    const orderQty = @json($pos->mapWithKeys(fn ($po) => [$po->id => $po->sizes->pluck('qty', 'size_id')]));
    const cutQty = @json($cutBySize);
    const select = document.getElementById('poSelect');
    function total() {
        let t = 0;
        document.querySelectorAll('[data-cut-qty]').forEach(i => t += parseInt(i.value || 0, 10));
        document.getElementById('cutTotal').textContent = t;
    }
    function hint() {
        const po = select.value, ordered = orderQty[po] || {}, cut = cutQty[po] || {};
        document.querySelectorAll('[data-size-info]').forEach(el => {
            const id = el.dataset.sizeInfo, o = ordered[id];
            el.textContent = o ? '(order ' + o + ', cut ' + (cut[id] || 0) + ')' : '';
        });
        document.getElementById('sizeHint').style.display = po ? 'none' : '';
    }
    document.addEventListener('input', e => { if (e.target.matches('[data-cut-qty]')) total(); });
    if (typeof $ !== 'undefined') { $(select).on('change', hint); } else { select.addEventListener('change', hint); }
    hint(); total();
})();
</script>
@endpush

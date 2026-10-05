@extends(adminTheme() . 'layouts.app')

@php
    use ME\MerchandisingSfl\Services\ProductionFlow;
    $label = ProductionFlow::label($stage);
    $usesLine = ProductionFlow::usesLine($stage);
    $oldDefects = old('defects', []);
@endphp

@section('title')
    <title>{{ websiteTitle('New ' . $label . ' Entry') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">New {{ $label }} Entry</h4>
            <a href="{{ route('msfl.production.entries.index', ['stage' => $stage]) }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            @if($pos->isEmpty())
                <div class="alert alert-warning">No confirmed order PO has {{ strtolower($label) }} on its route{{ in_array($stage, ['embroidery', 'washing']) ? ' — tick "Needs ' . $label . '" on the order PO' : '' }}.</div>
            @endif
            <form method="POST" action="{{ route('msfl.production.entries.store', ['stage' => $stage]) }}">
                @csrf
                <div class="row">
                    @include('merchandising-sfl::admin.production.partials.po-select', ['selected' => $selectedPo])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'entry_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'value' => now(), 'attrs' => 'max="' . now()->format('Y-m-d') . '"'])
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Size <small class="text-muted">(optional)</small></label>
                        <select name="size_id" class="form-control form-control-sm msfl-select2">
                            <option value="">All sizes</option>
                            @foreach($sizes as $size)<option value="{{ $size->id }}" @selected(old('size_id') == $size->id)>{{ $size->name }}</option>@endforeach
                        </select>
                    </div>
                    @if($partWise)
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Part <span class="text-danger">*</span> <small class="text-muted">(sent from cutting)</small></label>
                            <select name="part_name" id="partInput" class="form-control form-control-sm" required>
                                <option value="">— Select part —</option>
                                @foreach($garmentParts as $gp)<option value="{{ $gp }}" @selected(old('part_name') === $gp)>{{ $gp }}</option>@endforeach
                            </select>
                            <span class="form-text text-muted small" id="poPartHint"></span>
                            @error('part_name')<span class="form-text text-danger">{{ $message }}</span>@enderror
                        </div>
                    @endif
                    @if($usesLine)
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Line <span class="text-danger">*</span></label>
                            <select name="line_id" id="lineSelect" class="form-control form-control-sm msfl-select2" required>
                                <option value="">— Select line —</option>
                                @foreach($lines as $line)<option value="{{ $line->id }}" data-code="{{ strtolower(trim($line->code)) }}" @selected(old('line_id') == $line->id)>{{ $line->name }}</option>@endforeach
                            </select>
                            @if($lines->isEmpty())<span class="form-text text-danger">No line set up — Planning → Setup → Lines.</span>@endif
                        </div>
                    @endif
                </div>

                <div id="balanceBox" class="alert alert-light border small py-2" style="display:none"></div>

                <div class="row">
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'input_qty', 'label' => 'Input (pcs in)', 'type' => 'number', 'attrs' => 'data-qty'])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'pass_qty', 'label' => 'QC Pass', 'type' => 'number', 'attrs' => 'data-qty'])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'rework_qty', 'label' => 'Rework', 'type' => 'number', 'attrs' => 'data-qty data-defect-total="rework"'])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'reject_qty', 'label' => 'Reject', 'type' => 'number', 'attrs' => 'data-qty data-defect-total="reject"'])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'col' => 6])
                </div>
                <p class="small text-muted mt-n2">Rework pieces stay in {{ strtolower($label) }} until they pass; rejects leave the flow. Break every reject / rework down below — part and machine are optional (pick the machine to see machine-wise rejection).</p>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Reject / Rework Detail <small class="text-muted">(reject <span data-defect-sum="reject">0</span>, rework <span data-defect-sum="rework">0</span>)</small></h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-line-items-add="defect"><i class="fa-solid fa-plus"></i> Add Row</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th style="width:120px">Type</th><th>Part</th><th>Machine <small class="text-muted">(optional)</small></th><th>Defect</th><th style="width:110px">Pcs</th><th style="width:40px"></th></tr></thead>
                        <tbody id="defectRowsBody">
                            @foreach($oldDefects ?: [[]] as $i => $d)
                                @include('merchandising-sfl::admin.production.entries.defect-row', ['index' => $i, 'd' => $d])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <template id="defectRowTemplate">
                    @include('merchandising-sfl::admin.production.entries.defect-row', ['index' => '__INDEX__', 'd' => []])
                </template>

                <button type="submit" class="btn btn-primary mt-2 btn-sm">Save</button>
                <button type="submit" name="add_another" value="1" class="btn btn-outline-primary mt-2 btn-sm">Save &amp; Add Another</button>
                <a href="{{ route('msfl.production.entries.index', ['stage' => $stage]) }}" class="btn btn-light mt-2 btn-sm">Cancel</a>
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
    const balances = @json($balances);
    const poParts = @json($poParts);
    const partInput = document.getElementById('partInput');
    const label = @json($label);
    const poSelect = document.getElementById('poSelect');
    const box = document.getElementById('balanceBox');
    const jq = typeof $ !== 'undefined' ? $ : null;

    function showBalance() {
        let b = balances[poSelect.value];
        if (partInput) {
            // Part-wise stage: offer the PO's cut parts and show that part's balance.
            const cut = poParts[poSelect.value] || [];
            document.getElementById('poPartHint').textContent = cut.length ? 'Cut for this PO: ' + cut.join(', ') : '';
            const part = partInput.value.trim();
            b = b && part ? ((b.parts || {})[part] || b.newPart) : null;
        }
        if (! b) { box.style.display = 'none'; return; }
        box.style.display = '';
        box.innerHTML = 'Can take in: <strong>' + b.available + '</strong> pcs · In ' + label + ' now (WIP): <strong>' + b.wip + '</strong>'
            + ' · So far — in ' + b.input + ', pass ' + b.pass + ', rework ' + b.rework + ', reject ' + b.reject;
    }

    function defectSums() {
        ['reject', 'rework'].forEach(function (type) {
            let sum = 0;
            document.querySelectorAll('#defectRowsBody tr').forEach(function (row) {
                if (row.querySelector('[data-defect-type]').value === type) sum += parseInt(row.querySelector('[data-defect-qty]').value || 0, 10);
            });
            const el = document.querySelector('[data-defect-sum="' + type + '"]');
            const want = parseInt(document.querySelector('[data-defect-total="' + type + '"]').value || 0, 10);
            el.textContent = sum + (sum !== want ? ' ≠ ' + want : ' ✓');
            el.className = sum === want ? 'text-success' : 'text-danger';
        });
    }

    // Sewing: show only the Inventory machines on the chosen line.
    const lineSelect = document.getElementById('lineSelect');
    function filterMachines(scope) {
        if (! lineSelect) return;
        const code = lineSelect.selectedOptions[0]?.dataset.code || '';
        (scope || document).querySelectorAll('[data-machine-select]').forEach(function (sel) {
            Array.from(sel.options).forEach(function (o) {
                if (! o.value) return;
                const on = ! code || o.dataset.line === code || o.dataset.line === '';
                o.disabled = ! on;
                if (! on && o.selected) sel.value = '';
            });
            if (jq) jq(sel).trigger('change.select2');
        });
    }

    if (partInput) partInput.addEventListener('change', showBalance);
    if (jq) { jq(poSelect).on('change', showBalance); if (lineSelect) jq(lineSelect).on('change', () => filterMachines()); }
    document.addEventListener('input', function (e) { if (e.target.closest('#defectRowsBody') || e.target.matches('[data-defect-total]')) defectSums(); });
    document.addEventListener('change', function (e) { if (e.target.matches('[data-defect-type]')) defectSums(); });
    document.getElementById('defectRowsBody').addEventListener('msfl:rows-changed', function (e) { filterMachines(e.detail?.row); defectSums(); });
    showBalance(); defectSums(); filterMachines();
})();
</script>
@endpush

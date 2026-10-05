@extends(adminTheme() . 'layouts.app')

@php
    $oldDefects = old('defects', []);
    $kind = old('kind', 'qc');
    $stage = old('stage', $selectedStage);
@endphp

@section('title')
    <title>{{ websiteTitle('New Reject / Rework') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">New Reject / Rework</h4>
            <a href="{{ route('msfl.production.reject-rework.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.production.reject-rework.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Step <span class="text-danger">*</span></label>
                        <select name="stage" id="stageSelect" class="form-control form-control-sm" required>
                            <option value="">— Select step —</option>
                            @foreach($stages as $key => $label)<option value="{{ $key }}" @selected($stage === $key)>{{ $label }}</option>@endforeach
                        </select>
                        @error('stage')<span class="form-text text-danger">{{ $message }}</span>@enderror
                    </div>
                    @include('merchandising-sfl::admin.production.partials.po-select', ['selected' => $selectedPo])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'entry_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'value' => now(), 'attrs' => 'max="' . now()->format('Y-m-d') . '"'])
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Size <small class="text-muted">(optional)</small></label>
                        <select name="size_id" class="form-control form-control-sm msfl-select2">
                            <option value="">All sizes</option>
                            @foreach($sizes as $size)<option value="{{ $size->id }}" @selected(old('size_id') == $size->id)>{{ $size->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3" id="lineBox">
                        <label class="form-label">Line <span class="text-danger">*</span></label>
                        <select name="line_id" id="lineSelect" class="form-control form-control-sm msfl-select2">
                            <option value="">— Select line —</option>
                            @foreach($lines as $line)<option value="{{ $line->id }}" data-code="{{ strtolower(trim($line->code)) }}" @selected(old('line_id') == $line->id)>{{ $line->name }}</option>@endforeach
                        </select>
                        @error('line_id')<span class="form-text text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label d-block">What happened? <span class="text-danger">*</span></label>
                    <div class="btn-group btn-group-toggle" data-toggle="buttons">
                        <label class="btn btn-sm btn-outline-danger {{ $kind === 'qc' ? 'active' : '' }}">
                            <input type="radio" name="kind" value="qc" @checked($kind === 'qc')> Reject / Rework found
                        </label>
                        <label class="btn btn-sm btn-outline-success {{ $kind === 'rework' ? 'active' : '' }}">
                            <input type="radio" name="kind" value="rework" @checked($kind === 'rework')> Rework fixed
                        </label>
                    </div>
                    <div class="small text-muted mt-1" data-kind-note="qc">Among pieces this step already passed (not yet taken by the next step): rejects leave the flow, rework goes back to this step until fixed.</div>
                    <div class="small text-muted mt-1" data-kind-note="rework">Rework pieces waiting at this step: how many passed after fixing, how many had to be rejected.</div>
                </div>

                <div id="balanceBox" class="alert alert-light border small py-2" style="display:none"></div>

                <div class="row">
                    <div class="col-md-3 mb-3" data-kind-field="rework">
                        <label class="form-label">Passed after rework</label>
                        <input type="number" min="0" step="1" name="pass_qty" class="form-control form-control-sm" value="{{ old('pass_qty') }}">
                        @error('pass_qty')<span class="form-text text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-3 mb-3" data-kind-field="qc">
                        <label class="form-label">Rework</label>
                        <input type="number" min="0" step="1" name="rework_qty" class="form-control form-control-sm" value="{{ old('rework_qty') }}" data-defect-total="rework">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Reject</label>
                        <input type="number" min="0" step="1" name="reject_qty" class="form-control form-control-sm" value="{{ old('reject_qty') }}" data-defect-total="reject">
                        @error('reject_qty')<span class="form-text text-danger">{{ $message }}</span>@enderror
                    </div>
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks'])
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Reject / Rework Detail <small class="text-muted">(reject <span data-defect-sum="reject">0</span><span data-kind-field="qc">, rework <span data-defect-sum="rework">0</span></span>)</small></h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-line-items-add="defect"><i class="fa-solid fa-plus"></i> Add Row</button>
                </div>
                @error('defects')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
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
                <a href="{{ route('msfl.production.reject-rework.index') }}" class="btn btn-light mt-2 btn-sm">Cancel</a>
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
    const stageSelect = document.getElementById('stageSelect');
    const poSelect = document.getElementById('poSelect');
    const lineSelect = document.getElementById('lineSelect');
    const box = document.getElementById('balanceBox');
    const jq = typeof $ !== 'undefined' ? $ : null;
    const kind = () => document.querySelector('input[name="kind"]:checked')?.value || 'qc';
    const sewing = () => stageSelect.value === 'sewing';

    function refresh() {
        const k = kind();
        document.querySelectorAll('[data-kind-field]').forEach(el => el.style.display = el.dataset.kindField === k ? '' : 'none');
        document.querySelectorAll('[data-kind-note]').forEach(el => el.style.display = el.dataset.kindNote === k ? '' : 'none');
        // Fixed rework: only reject rows make sense.
        document.querySelectorAll('[data-defect-type] option[value="rework"]').forEach(o => {
            o.disabled = k === 'rework';
            if (o.disabled && o.selected) o.parentElement.value = 'reject';
        });
        document.getElementById('lineBox').style.display = sewing() ? '' : 'none';
        lineSelect.required = sewing();

        const b = (balances[poSelect.value] || {})[stageSelect.value];
        if (! stageSelect.value || ! poSelect.value) { box.style.display = 'none'; }
        else if (! b) { box.style.display = ''; box.innerHTML = '<span class="text-danger">This PO has no ' + stageSelect.selectedOptions[0].text.toLowerCase() + ' on its route.</span>'; }
        else {
            box.style.display = '';
            box.innerHTML = k === 'qc'
                ? 'Passed pcs still in this step: <strong>' + b.ready + '</strong> — reject + rework can be up to this.'
                : 'Pcs waiting in this step (rework / WIP): <strong>' + b.wip + '</strong> — passed + reject can be up to this.';
        }
        defectSums();
    }

    function defectSums() {
        ['reject', 'rework'].forEach(function (type) {
            let sum = 0;
            document.querySelectorAll('#defectRowsBody tr').forEach(function (row) {
                if (row.querySelector('[data-defect-type]').value === type) sum += parseInt(row.querySelector('[data-defect-qty]').value || 0, 10);
            });
            const el = document.querySelector('[data-defect-sum="' + type + '"]');
            const input = document.querySelector('[data-defect-total="' + type + '"]');
            const want = type === 'rework' && kind() === 'rework' ? 0 : parseInt(input.value || 0, 10);
            el.textContent = sum + (sum !== want ? ' ≠ ' + want : ' ✓');
            el.className = sum === want ? 'text-success' : 'text-danger';
        });
    }

    // When a line is chosen (sewing), show only the Inventory machines on it.
    function filterMachines(scope) {
        const code = lineSelect.selectedOptions[0]?.dataset.code || '';
        (scope || document).querySelectorAll('[data-machine-select]').forEach(function (sel) {
            Array.from(sel.options).forEach(function (o) {
                if (! o.value) return;
                const on = ! code || o.dataset.line === code || o.dataset.line === '';
                o.disabled = ! on;
                if (! on && o.selected) sel.value = '';
            });
        });
    }

    stageSelect.addEventListener('change', refresh);
    document.querySelectorAll('input[name="kind"]').forEach(r => r.addEventListener('change', refresh));
    if (jq) { jq(poSelect).on('change', refresh); jq(lineSelect).on('change', () => filterMachines()); }
    document.addEventListener('input', function (e) { if (e.target.closest('#defectRowsBody') || e.target.matches('[data-defect-total]')) defectSums(); });
    document.addEventListener('change', function (e) { if (e.target.matches('[data-defect-type]')) defectSums(); });
    document.getElementById('defectRowsBody').addEventListener('msfl:rows-changed', function (e) { filterMachines(e.detail?.row); refresh(); });
    refresh(); filterMachines();
})();
</script>
@endpush

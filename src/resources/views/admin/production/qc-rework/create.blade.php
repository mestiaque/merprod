@extends(adminTheme() . 'layouts.app')

@php
    use ME\MerchandisingSfl\Services\ProductionFlow;
    $oldDefects = old('defects', []);
    $title = ProductionFlow::label($stage) . ' ' . ($kind === 'qc' ? 'QC' : 'Rework');
    $usesLine = ProductionFlow::usesLine($stage);
@endphp

@section('title')
    <title>{{ websiteTitle($title) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $title }}</h4>
            <a href="{{ route('msfl.production.' . $kind . '.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            @if($pos->isEmpty())
                <div class="alert alert-warning">No confirmed order PO has {{ strtolower(ProductionFlow::label($stage)) }} on its route.</div>
            @endif
            <p class="small text-muted">{{ $kind === 'qc'
                ? 'Pieces this step passed (not yet taken by the next step): rejects leave the flow, rework stays here until fixed — then enter it in Rework.'
                : 'Rework pieces waiting in this step: how many passed after fixing, how many had to be rejected.' }}</p>

            <form method="POST" action="{{ route('msfl.production.' . $kind . '.store') }}">
                @csrf
                <input type="hidden" name="stage" value="{{ $stage }}">
                <div class="row">
                    @include('merchandising-sfl::admin.production.partials.po-select', ['selected' => $selectedPo])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'entry_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'value' => now(), 'attrs' => 'max="' . now()->format('Y-m-d') . '"'])
                    @if($partWise)
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Part <span class="text-danger">*</span></label>
                            <select name="part_name" id="partInput" class="form-control form-control-sm" required>
                                <option value="">— Select part —</option>
                                @foreach($garmentParts as $gp)<option value="{{ $gp }}" @selected(old('part_name') === $gp)>{{ $gp }}</option>@endforeach
                            </select>
                            @error('part_name')<span class="form-text text-danger">{{ $message }}</span>@enderror
                        </div>
                    @endif
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Size <small class="text-muted">(optional)</small></label>
                        <select name="size_id" class="form-control form-control-sm msfl-select2">
                            <option value="">All sizes</option>
                            @foreach($sizes as $size)<option value="{{ $size->id }}" @selected(old('size_id') == $size->id)>{{ $size->name }}</option>@endforeach
                        </select>
                    </div>
                    @if($usesLine)
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Line <span class="text-danger">*</span></label>
                            <select name="line_id" id="lineSelect" class="form-control form-control-sm msfl-select2" required>
                                <option value="">— Select line —</option>
                                @foreach($lines as $line)<option value="{{ $line->id }}" data-code="{{ strtolower(trim($line->code)) }}" @selected(old('line_id') == $line->id)>{{ $line->name }}</option>@endforeach
                            </select>
                            @error('line_id')<span class="form-text text-danger">{{ $message }}</span>@enderror
                        </div>
                    @endif
                </div>

                <div id="balanceBox" class="alert alert-light border small py-2" style="display:none"></div>

                <div class="row">
                    @if($kind === 'rework')
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Passed after rework</label>
                            <input type="number" min="0" step="1" name="pass_qty" class="form-control form-control-sm" value="{{ old('pass_qty') }}">
                            @error('pass_qty')<span class="form-text text-danger">{{ $message }}</span>@enderror
                        </div>
                    @else
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Rework</label>
                            <input type="number" min="0" step="1" name="rework_qty" class="form-control form-control-sm" value="{{ old('rework_qty') }}" data-defect-total="rework">
                        </div>
                    @endif
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Reject</label>
                        <input type="number" min="0" step="1" name="reject_qty" class="form-control form-control-sm" value="{{ old('reject_qty') }}" data-defect-total="reject">
                        @error('reject_qty')<span class="form-text text-danger">{{ $message }}</span>@enderror
                    </div>
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks'])
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Reject / Rework Detail <small class="text-muted">(reject <span data-defect-sum="reject">0</span>@if($kind === 'qc'), rework <span data-defect-sum="rework">0</span>@endif)</small></h6>
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
                <a href="{{ route('msfl.production.' . $kind . '.index') }}" class="btn btn-light mt-2 btn-sm">Cancel</a>
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
    const kind = @json($kind);
    const poSelect = document.getElementById('poSelect');
    const partInput = document.getElementById('partInput');
    const lineSelect = document.getElementById('lineSelect');
    const box = document.getElementById('balanceBox');
    const jq = typeof $ !== 'undefined' ? $ : null;

    function refresh() {
        // Rework screen: only reject rows make sense.
        document.querySelectorAll('[data-defect-type] option[value="rework"]').forEach(o => {
            o.disabled = kind === 'rework';
            if (o.disabled && o.selected) o.parentElement.value = 'reject';
        });

        let b = balances[poSelect.value];
        if (partInput) {
            const part = partInput.value.trim();
            b = b && part ? ((b.parts || {})[part] || {ready: 0, wip: 0}) : null;
        }
        if (! b) { box.style.display = 'none'; }
        else {
            box.style.display = '';
            box.innerHTML = kind === 'qc'
                ? 'Passed pcs still in this step: <strong>' + b.ready + '</strong> — reject + rework can be up to this.'
                : 'Pcs waiting in this step: <strong>' + b.wip + '</strong> — passed + reject can be up to this.';
        }
        defectSums();
    }

    function defectSums() {
        ['reject', 'rework'].forEach(function (type) {
            const el = document.querySelector('[data-defect-sum="' + type + '"]');
            if (! el) return;
            let sum = 0;
            document.querySelectorAll('#defectRowsBody tr').forEach(function (row) {
                if (row.querySelector('[data-defect-type]').value === type) sum += parseInt(row.querySelector('[data-defect-qty]').value || 0, 10);
            });
            const want = parseInt(document.querySelector('[data-defect-total="' + type + '"]')?.value || 0, 10);
            el.textContent = sum + (sum !== want ? ' ≠ ' + want : ' ✓');
            el.className = sum === want ? 'text-success' : 'text-danger';
        });
    }

    // Sewing: show only the Inventory machines on the chosen line.
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
        });
    }

    if (partInput) partInput.addEventListener('change', refresh);
    if (jq) { jq(poSelect).on('change', refresh); if (lineSelect) jq(lineSelect).on('change', () => filterMachines()); }
    document.addEventListener('input', function (e) { if (e.target.closest('#defectRowsBody') || e.target.matches('[data-defect-total]')) defectSums(); });
    document.addEventListener('change', function (e) { if (e.target.matches('[data-defect-type]')) defectSums(); });
    document.getElementById('defectRowsBody').addEventListener('msfl:rows-changed', function (e) { filterMachines(e.detail?.row); refresh(); });
    refresh(); filterMachines();
})();
</script>
@endpush

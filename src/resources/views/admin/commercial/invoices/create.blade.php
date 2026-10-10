@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('New Commercial Invoice') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">New Commercial Invoice</h4>
            <a href="{{ route('msfl.commercial.invoices.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            {{-- 1. Pick the LC (and optionally an Inventory shipment) — the page reloads with its POs. --}}
            <form method="GET" class="row align-items-end mb-2">
                <div class="col-md-5 mb-2">
                    <label class="form-label">Export LC / Sales Contract <span class="text-danger">*</span> <small class="text-muted">(active)</small></label>
                    <select name="export_lc_id" class="form-control form-control-sm msfl-select2" onchange="this.form.submit()">
                        <option value="">— Select —</option>
                        @foreach($lcs as $l)<option value="{{ $l->id }}" @selected($lc?->id === $l->id)>{{ $l->label() }}</option>@endforeach
                    </select>
                </div>
                @if($lc && $shipments->isNotEmpty())
                    <div class="col-md-5 mb-2">
                        <label class="form-label">Fill qty from Inventory Shipment <small class="text-muted">(optional)</small></label>
                        <select name="inv_shipment_id" class="form-control form-control-sm msfl-select2" onchange="this.form.submit()">
                            <option value="">— None —</option>
                            @foreach($shipments as $s)<option value="{{ $s['id'] }}" @selected($shipmentId == $s['id'])>{{ $s['label'] }}</option>@endforeach
                        </select>
                    </div>
                @endif
            </form>

            @if($lc)
                <div class="alert alert-light border small py-2">{{ $lc->label() }} · LC value {{ $lc->currency->code ?? '' }} {{ number_format((float) $lc->lc_value, 2) }} · last shipment {{ $lc->last_shipment_date?->format('d M Y') ?? '-' }} · expiry {{ $lc->expiry_date?->format('d M Y') ?? '-' }}</div>
                <form method="POST" action="{{ route('msfl.commercial.invoices.store') }}">
                    @csrf
                    @include('merchandising-sfl::admin.commercial.invoices.partials.form')
                    <button type="submit" class="btn btn-primary mt-3 btn-sm">Save Invoice</button>
                    <a href="{{ route('msfl.commercial.invoices.index') }}" class="btn btn-light mt-3 btn-sm">Cancel</a>
                </form>
            @else
                <p class="text-muted small mb-0">Pick an active Export LC — its POs with the qty still to invoice show up. No LC? Make one in Commercial → Export LC and activate it.</p>
            @endif
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.select2-init')
@endsection

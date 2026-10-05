@extends(adminTheme() . 'layouts.app')

@php $label = \ME\MerchandisingSfl\Services\ProductionFlow::label($stage); @endphp

@section('title')
    <title>{{ websiteTitle($label) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $label }}</h4>
            @can('msfl_prod_entry.add')
                <a href="{{ route('msfl.production.entries.create', ['stage' => $stage]) }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New {{ $label }} Entry</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                @include('merchandising-sfl::admin.production.partials.po-select', ['filter' => true, 'col' => 4])
                <div class="col-md-2 mb-2"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}" title="From"></div>
                <div class="col-md-2 mb-2"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}" title="To"></div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('msfl.production.entries.index', ['stage' => $stage]) }}" class="btn btn-light btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                @include('merchandising-sfl::admin.production.partials.entries-table', ['entries' => $entries, 'showStage' => false, 'showPo' => true])
            </div>
            {{ $entries->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection

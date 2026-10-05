@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Reject & Rework') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Reject &amp; Rework</h4>
            @can('msfl_prod_entry.add')
                <a href="{{ route('msfl.production.reject-rework.create', array_filter(['stage' => request('stage')])) }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New Reject / Rework</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-2 mb-2">
                    <select name="stage" class="form-control form-control-sm">
                        <option value="">All steps</option>
                        @foreach($stages as $key => $label)<option value="{{ $key }}" @selected(request('stage') === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                @include('merchandising-sfl::admin.production.partials.po-select', ['filter' => true, 'col' => 4])
                <div class="col-md-2 mb-2"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}" title="From"></div>
                <div class="col-md-2 mb-2"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}" title="To"></div>
                <div class="col-md-2 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('msfl.production.reject-rework.index') }}" class="btn btn-light btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                @include('merchandising-sfl::admin.production.partials.entries-table', ['entries' => $entries, 'showStage' => true, 'showPo' => true, 'deleteRoute' => 'msfl.production.reject-rework.destroy'])
            </div>
            {{ $entries->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection

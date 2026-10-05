@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle($title) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $title }} @isset($result['period'])<small class="text-muted">— {{ $result['period'] }}</small>@endisset</h4>
            <div class="d-flex gap-1">
                <a href="{{ route('msfl.reports.print', ['report' => $key] + request()->query()) }}" target="_blank" class="btn btn-primary btn-sm"><i class="fa-solid fa-print"></i> Print</a>
                <a href="{{ route('msfl.reports.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Reports</a>
            </div>
        </div>
        <div class="card-body">
            <p class="small text-muted">{{ $description }}</p>
            <form method="GET" class="row mb-3 align-items-end">
                @if(in_array('buyer', $filters, true))
                    <div class="col-md-3 mb-2"><select name="buyer_id" class="form-control form-control-sm msfl-select2"><option value="">All Buyers</option>
                        @foreach($buyers as $b)<option value="{{ $b->id }}" @selected(request('buyer_id') == $b->id)>{{ $b->name }}</option>@endforeach</select></div>
                @endif
                @if(in_array('style', $filters, true))
                    <div class="col-md-2 mb-2"><select name="style_id" class="form-control form-control-sm msfl-select2"><option value="">All Styles</option>
                        @foreach($styles as $st)<option value="{{ $st->id }}" @selected(request('style_id') == $st->id)>{{ $st->style_no }} — {{ $st->name }}</option>@endforeach</select></div>
                @endif
                @if(in_array('dates', $filters, true))
                    <div class="col-md-2 mb-2"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}" title="From"></div>
                    <div class="col-md-2 mb-2"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}" title="To"></div>
                @endif
                @if(in_array('stage', $filters, true))
                    <div class="col-md-2 mb-2"><select name="stage" class="form-control form-control-sm msfl-select2"><option value="">All stages</option>
                        @foreach($stages as $k => $l)<option value="{{ $k }}" @selected(request('stage') === $k)>{{ $l }}</option>@endforeach</select></div>
                @endif
                @if(in_array('line', $filters, true))
                    <div class="col-md-3 mb-2"><select name="line_id" class="form-control form-control-sm msfl-select2"><option value="">All lines</option>
                        @foreach($lines as $l)<option value="{{ $l->id }}" @selected(request('line_id') == $l->id)>{{ $l->name }}</option>@endforeach</select></div>
                @endif
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('msfl.reports.show', $key) }}" class="btn btn-light btn-sm">Reset</a>
                </div>
            </form>
            <div class="table-responsive">@include('merchandising-sfl::admin.reports.partials.table', ['result' => $result])</div>
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.select2-init')
@endsection

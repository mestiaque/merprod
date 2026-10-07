@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Hourly Production Report') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')
    @include('merchandising-sfl::admin.production.sewing.partials.hourly-style')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h4 class="mb-0">Daily Hourly Production Report <small class="text-muted">Sewing Floor · {{ $date->format('d M Y') }}</small></h4>
            <div>
                <a href="{{ route('msfl.production.sewing.index', request()->only('date', 'line_id')) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-shirt"></i> Sewing Board</a>
                <a href="{{ route('msfl.production.hourly-report.print', request()->only('date', 'line_id')) }}" target="_blank" class="btn btn-warning btn-sm"><i class="fa-solid fa-print"></i> Print</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2"><input type="date" name="date" class="form-control form-control-sm" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}"></div>
                <div class="col-md-3 mb-2">
                    <select name="line_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All lines</option>
                        @foreach($lines as $line)<option value="{{ $line->id }}" @selected(request('line_id') == $line->id)>{{ $line->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('msfl.production.hourly-report.index') }}" class="btn btn-light btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                @include('merchandising-sfl::admin.production.sewing.partials.hourly-table')
            </div>
            <p class="small text-muted mb-0 mt-2">
                Efficiency (per hour) = output × SMV ÷ (man power × 60) × 100 · DHU = (reject + rework) ÷ checked × 100 ·
                Target Eff % = target × SMV ÷ (man power × hours × 60) × 100 · Running Day = days the line has worked on this style · Red = below hourly target.
                Entries are made on the <a href="{{ route('msfl.production.sewing.index') }}">Sewing board</a>.
            </p>
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.select2-init')
@endsection

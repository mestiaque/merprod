@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('New Export LC / Sales Contract') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ 'New Export LC / Sales Contract' }}</h4>
            <a href="{{ route('msfl.commercial.export-lcs.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.commercial.export-lcs.store') }}" enctype="multipart/form-data">
                @csrf
                @include('merchandising-sfl::admin.commercial.export-lcs.partials.form')
                <button type="submit" class="btn btn-primary mt-3 btn-sm">Save</button>
                <a href="{{ route('msfl.commercial.export-lcs.index') }}" class="btn btn-light mt-3 btn-sm">Cancel</a>
            </form>
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.select2-init')
@endsection

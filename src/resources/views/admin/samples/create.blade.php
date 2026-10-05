@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('New Sample Request') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ 'New Sample Request' }}</h4>
            <a href="{{ route('msfl.samples.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.samples.store') }}" enctype="multipart/form-data">
                @csrf
                @include('merchandising-sfl::admin.samples.partials.form')
                <button type="submit" class="btn btn-primary mt-3 btn-sm">Save Sample</button>
                <a href="{{ route('msfl.samples.index') }}" class="btn btn-light mt-3 btn-sm">Cancel</a>
            </form>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection

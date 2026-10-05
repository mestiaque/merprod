@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('New Tech Pack / Style') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ 'New Tech Pack / Style' }}</h4>
            <a href="{{ route('msfl.styles.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.styles.store') }}" enctype="multipart/form-data">
                @csrf
                @include('merchandising-sfl::admin.styles.partials.form')
                <button type="submit" class="btn btn-primary mt-3 btn-sm">Save Style</button>
                <a href="{{ route('msfl.styles.index') }}" class="btn btn-light mt-3 btn-sm">Cancel</a>
            </form>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection

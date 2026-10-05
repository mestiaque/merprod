@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Edit BOM — ' . $bom->bom_no) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ 'Edit BOM — ' . $bom->bom_no }}</h4>
            <a href="{{ route('msfl.boms.show', $bom) }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.boms.update', $bom) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                @include('merchandising-sfl::admin.boms.partials.form')
                <button type="submit" class="btn btn-primary mt-3 btn-sm">Update BOM</button>
                <a href="{{ route('msfl.boms.show', $bom) }}" class="btn btn-light mt-3 btn-sm">Cancel</a>
            </form>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection

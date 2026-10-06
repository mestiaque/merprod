@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('New Tech Pack') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ 'New Tech Pack' }}</h4>
            <a href="{{ route('msfl.styles.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.styles.store') }}" enctype="multipart/form-data">
                @csrf
                @include('merchandising-sfl::admin.styles.partials.form')
                <button type="submit" class="btn btn-primary mt-3 btn-sm">Save Tech Pack</button>
                <a href="{{ route('msfl.styles.index') }}" class="btn btn-light mt-3 btn-sm">Cancel</a>
            </form>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@push('js')
<script>
    // Picking a master style fills Season / Product Type from Master Data (when still empty).
    $(function () {
        $('#techPackStyle').on('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) { return; }
            [['season_id', opt.dataset.season], ['product_type_id', opt.dataset.productType]].forEach(function (pair) {
                const field = $('[name="' + pair[0] + '"]');
                if (pair[1] && !field.val()) { field.val(pair[1]).trigger('change'); }
            });
        });
    });
</script>
@endpush
@endsection

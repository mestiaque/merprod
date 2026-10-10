@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Edit Invoice ' . $invoice->invoice_no) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Edit Commercial Invoice — {{ $invoice->invoice_no }}</h4>
            <a href="{{ route('msfl.commercial.invoices.show', $invoice) }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <div class="alert alert-light border small py-2">{{ $lc->label() }}</div>
            <form method="POST" action="{{ route('msfl.commercial.invoices.update', $invoice) }}">
                @csrf @method('PUT')
                @include('merchandising-sfl::admin.commercial.invoices.partials.form')
                <button type="submit" class="btn btn-primary mt-3 btn-sm">Update Invoice</button>
                <a href="{{ route('msfl.commercial.invoices.show', $invoice) }}" class="btn btn-light mt-3 btn-sm">Cancel</a>
            </form>
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.select2-init')
@endsection

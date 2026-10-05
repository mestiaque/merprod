@extends('printMaster2')

@section('title', 'Post Cost Sheet — ' . $c['po']->po_no)

@push('css')<style>@page { size: A4 portrait; margin: 10mm; } .container { max-width: 1000px; }</style>@endpush

@section('contents')
    <div class="print-header">
        <div class="company-info">
            @if(general() && general()->logo())<img src="{{ asset(general()->logo()) }}" alt="Logo" class="company-logo">@endif
            <div class="company-name">{{ general()->title ?? '' }}</div>
            <div style="text-align: end; width: 42mm;"><div class="company-address">{{ general()->address_one ?? '' }}</div></div>
        </div>
        <div style="font-weight: bold; text-transform: uppercase;">Post Cost Sheet (Budget vs Actual) <span class="print-time"><i>{{ now()->format('d-m-Y H:i:s') }}</i></span></div>
    </div>
    @include('merchandising-sfl::admin.post-costing.partials.sheet', ['forPrint' => true])
@endsection

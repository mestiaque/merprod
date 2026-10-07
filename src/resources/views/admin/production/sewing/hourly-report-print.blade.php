@extends('printMaster2')

@section('title', 'Daily Hourly Production Report')

@push('css')
<style>
    @page { size: A4 landscape; margin: 6mm; }
    .container { max-width: none; }
    table.report-table { width: 100%; border-collapse: collapse; }
    table.report-table th, table.report-table td { border: 1px solid #333; padding: 2px 3px; font-size: 9.5px; color: #000; }
    table.report-table thead th { background: #e9ecef; font-size: 8.5px; } .text-right { text-align: right; } .text-center { text-align: center; }
    tfoot td { font-weight: 700; background: #fff59d; }
    @media print { thead th, tfoot td, .sew-break, .hr-hours, .hr-efficiency, .hr-process, .sew-low { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@endpush

@section('contents')
    @include('merchandising-sfl::admin.production.sewing.partials.hourly-style')
    <div class="print-header">
        <div class="company-info">
            @if(general() && general()->logo())<img src="{{ asset(general()->logo()) }}" alt="Logo" class="company-logo">@endif
            <div class="company-name">{{ general()->title ?? '' }}</div>
            <div style="text-align: end; width: 42mm;"><div class="company-address">{{ general()->address_one ?? '' }}</div></div>
        </div>
        <div style="font-weight: bold; text-transform: uppercase;">Daily Hourly Production Report — Sewing Floor ({{ $date->format('d-M-Y') }}) <span class="print-time"><i>{{ now()->format('d-m-Y H:i:s') }}</i></span></div>
    </div>
    @include('merchandising-sfl::admin.production.sewing.partials.hourly-table', ['print' => true])
@endsection

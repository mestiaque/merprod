@extends('printMaster2')

@section('title', $title)

@push('css')
<style>
    @page { size: A4 landscape; margin: 8mm; }
    .container { max-width: none; }
    table.report-table { width: 100%; border-collapse: collapse; }
    table.report-table th, table.report-table td { border: 1px solid #555; padding: 3px 5px; font-size: 10.5px; color: #000; }
    table.report-table thead th { background: #e9ecef; } .text-right { text-align: right; } .text-center { text-align: center; }
    .table-danger td { background: #fee2e2; } .table-warning td { background: #fef3c7; } .table-success td { background: #dcfce7; }
    tfoot td { font-weight: 700; background: #f3f4f6; }
    @media print { thead th, .table-danger td, .table-warning td, .table-success td, tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@endpush

@section('contents')
    <div class="print-header">
        <div class="company-info">
            @if(general() && general()->logo())<img src="{{ asset(general()->logo()) }}" alt="Logo" class="company-logo">@endif
            <div class="company-name">{{ general()->title ?? '' }}</div>
            <div style="text-align: end; width: 42mm;"><div class="company-address">{{ general()->address_one ?? '' }}</div></div>
        </div>
        <div style="font-weight: bold; text-transform: uppercase;">{{ $title }} @isset($result['period'])({{ $result['period'] }})@endisset <span class="print-time"><i>{{ now()->format('d-m-Y H:i:s') }}</i></span></div>
    </div>
    @include('merchandising-sfl::admin.reports.partials.table', ['result' => $result])
@endsection

@extends('printMaster2')

@section('title', 'Sewing Daily Production')

@push('css')
<style>
    @page { size: A3 landscape; margin: 6mm; }
    .container { max-width: none; }
    table.report-table { width: 100%; border-collapse: collapse; }
    table.report-table th, table.report-table td { border: 1px solid #555; padding: 2px 3px; font-size: 9px; color: #000; }
    table.report-table thead th { background: #e9ecef; font-size: 8px; } .text-right { text-align: right; } .text-center { text-align: center; }
    .sew-break { background: #fdecec; } .sew-plan { background: #fef9e7; } .text-muted { color: #666; } .text-danger { color: #b91c1c; } .text-success { color: #15803d; }
    tfoot td { font-weight: 700; background: #f3f4f6; }
    @media print { thead th, tfoot td, .sew-break, .sew-plan { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@endpush

@section('contents')
    <div class="print-header">
        <div class="company-info">
            @if(general() && general()->logo())<img src="{{ asset(general()->logo()) }}" alt="Logo" class="company-logo">@endif
            <div class="company-name">{{ general()->title ?? '' }}</div>
            <div style="text-align: end; width: 42mm;"><div class="company-address">{{ general()->address_one ?? '' }}</div></div>
        </div>
        <div style="font-weight: bold; text-transform: uppercase;">Sewing Daily Production ({{ $date->format('d-M-Y') }}) <span class="print-time"><i>{{ now()->format('d-m-Y H:i:s') }}</i></span></div>
    </div>
    @include('merchandising-sfl::admin.production.sewing.partials.board-table', ['board' => $board, 'slots' => $slots, 'breakHour' => $breakHour, 'print' => true])
@endsection

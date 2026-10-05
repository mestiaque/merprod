@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Cost Sheet ' . $costSheet->cost_sheet_no) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Cost Sheet — {{ $costSheet->cost_sheet_no }}</h4>
            <div>
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-print"></i> Print</button>
                @if($costSheet->isEditable())
                    @can('msfl_cost_sheet.edit')
                        <a href="{{ route('msfl.cost-sheets.edit', $costSheet) }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>
                    @endcan
                    @can('msfl_cost_sheet.approve')
                        <form method="POST" action="{{ route('msfl.cost-sheets.approve', $costSheet) }}" class="d-inline" onsubmit="return confirm('Approve this cost sheet? It cannot be edited afterwards.');">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm"><i class="fa-solid fa-check"></i> Approve</button>
                        </form>
                    @endcan
                @endif
                <a href="{{ route('msfl.cost-sheets.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $costSheet->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Style:</strong>
                    @if($costSheet->style)<a href="{{ route('msfl.styles.show', $costSheet->style) }}">{{ $costSheet->style->label() }}</a>@else {{ $costSheet->style_ref ?? '-' }} @endif
                </div>
                <div class="col-md-3 mb-2"><strong>Inquiry:</strong>
                    @if($costSheet->inquiry)<a href="{{ route('msfl.inquiries.show', $costSheet->inquiry) }}">{{ $costSheet->inquiry->inquiry_no }}</a>@else - @endif
                </div>
                <div class="col-md-3 mb-2"><strong>Status:</strong> @include('merchandising-sfl::admin.partials.status-badge', ['model' => $costSheet])
                    @if($costSheet->approver)<small class="text-muted">by {{ $costSheet->approver->name }}, {{ $costSheet->approved_at->format('d M Y') }}</small>@endif
                </div>
                <div class="col-md-3 mb-2"><strong>Costing Date:</strong> {{ $costSheet->costing_date->format('d M Y') }}</div>
                <div class="col-md-3 mb-2"><strong>Garment:</strong> {{ $costSheet->garment_description ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Size Range:</strong> {{ $costSheet->size_range ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Currency:</strong> {{ $costSheet->currency->code ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Order Qty:</strong> {{ $costSheet->order_qty !== null ? number_format($costSheet->order_qty) : '-' }}</div>
                <div class="col-md-3 mb-2"><strong>SMV:</strong> {{ $costSheet->smv ?? '-' }}</div>
            </div>

            @foreach(\ME\MerchandisingSfl\Models\CostSheet::GROUPS as $group => $groupLabel)
                @php $groupLines = $costSheet->items->where('group', $group); @endphp
                @continue($groupLines->isEmpty())
                <h6>{{ $groupLabel }}</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th>Item</th><th>Description</th><th>Unit</th><th class="text-right">Cons / dz</th><th class="text-right">Rate</th><th class="text-right">Amount / dz</th><th>Remarks</th></tr></thead>
                        <tbody>
                            @foreach($groupLines as $line)
                                <tr>
                                    <td>{{ $line->item->name ?? '-' }}</td>
                                    <td>{{ $line->description ?? '-' }}</td>
                                    <td>{{ $line->uom->code ?? '-' }}</td>
                                    <td class="text-right">{{ rtrim(rtrim(number_format($line->consumption, 4), '0'), '.') }}</td>
                                    <td class="text-right">{{ number_format($line->rate, 4) }}</td>
                                    <td class="text-right">{{ number_format($line->amount, 4) }}</td>
                                    <td>{{ $line->remarks }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th colspan="5" class="text-right">Total</th><th class="text-right">{{ number_format($costSheet->{$group . '_cost'}, 4) }}</th><th></th></tr></tfoot>
                    </table>
                </div>
            @endforeach

            <div class="row">
                <div class="col-md-6">
                    @if($costSheet->remarks)
                        <strong>Remarks:</strong> {!! nl2br(e($costSheet->remarks)) !!}
                    @endif
                </div>
                <div class="col-md-6">
                    <table class="table table-bordered table-sm">
                        <tbody>
                            <tr><th>Materials / dz</th><td class="text-right">{{ number_format($costSheet->materialCost(), 4) }}</td></tr>
                            <tr><th>CM / dz</th><td class="text-right">{{ number_format($costSheet->cm_cost, 4) }}</td></tr>
                            <tr><th>Commercial ({{ $costSheet->commercial_percent }}% on materials) / dz</th><td class="text-right">{{ number_format($costSheet->commercial_cost, 4) }}</td></tr>
                            <tr><th>Other / dz</th><td class="text-right">{{ number_format($costSheet->other_cost, 4) }}</td></tr>
                            <tr><th>Total Cost / dz</th><td class="text-right font-weight-bold">{{ number_format($costSheet->total_cost, 4) }}</td></tr>
                            <tr><th>Profit ({{ $costSheet->profit_percent }}%) / dz</th><td class="text-right">{{ number_format($costSheet->profit_amount, 4) }}</td></tr>
                            <tr class="table-success"><th>Offer Price / pc</th><td class="text-right font-weight-bold">{{ number_format($costSheet->offer_price, 4) }}</td></tr>
                            <tr><th>Buyer Target / pc</th><td class="text-right">{{ $costSheet->buyer_target_price !== null ? number_format($costSheet->buyer_target_price, 4) : '-' }}</td></tr>
                            <tr><th>Final (Agreed) / pc</th><td class="text-right font-weight-bold">{{ $costSheet->final_price !== null ? number_format($costSheet->final_price, 4) : '-' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

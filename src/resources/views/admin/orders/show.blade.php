@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Order ' . $order->order_no) }}</title>
@endsection

@php
    $canEditPos = $order->isEditable() && auth()->user()->can('msfl_order.edit');
    $statusActions = ['confirmed' => ['Confirm Order', 'success', 'fa-check', 'msfl_order.approve'], 'closed' => ['Close', 'dark', 'fa-lock', 'msfl_order.edit'], 'cancelled' => ['Cancel Order', 'danger', 'fa-ban', 'msfl_order.edit']];
@endphp

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Order — {{ $order->order_no }}</h4>
            <div>
                @foreach($statusActions as $status => [$label, $color, $icon, $permission])
                    @if($order->canMoveTo($status))
                        @can($permission)
                            <form method="POST" action="{{ route('msfl.orders.status', $order) }}" class="d-inline" onsubmit="return confirm('{{ $label }}?');">
                                @csrf
                                <input type="hidden" name="status" value="{{ $status }}">
                                <button type="submit" class="btn btn-{{ $color }} btn-sm"><i class="fa-solid {{ $icon }}"></i> {{ $label }}</button>
                            </form>
                        @endcan
                    @endif
                @endforeach
                @if($order->isEditable())
                    @can('msfl_order.edit')
                        <a href="{{ route('msfl.orders.edit', $order) }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>
                    @endcan
                @endif
                <a href="{{ route('msfl.orders.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $order->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Order Date:</strong> {{ $order->order_date->format('d M Y') }}</div>
                <div class="col-md-3 mb-2"><strong>Buyer Ref:</strong> {{ $order->buyer_order_ref ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Status:</strong> @include('merchandising-sfl::admin.partials.status-badge', ['model' => $order])
                    @if($order->confirmer)<small class="text-muted">by {{ $order->confirmer->name }}, {{ $order->confirmed_at->format('d M Y') }}</small>@endif
                </div>
                <div class="col-md-3 mb-2"><strong>Season:</strong> {{ $order->season->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Merchandiser:</strong> {{ $order->merchandiser->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Factory:</strong> {{ $order->factory->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Inquiry:</strong>
                    @if($order->inquiry)<a href="{{ route('msfl.inquiries.show', $order->inquiry) }}">{{ $order->inquiry->inquiry_no }}</a>@else - @endif
                </div>
                <div class="col-md-3 mb-2"><strong>Delivery / Payment:</strong> {{ $order->delivery_term ?? '-' }} / {{ $order->payment_term ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Total Qty:</strong> {{ number_format($order->total_qty) }} pcs</div>
                <div class="col-md-3 mb-2"><strong>Total Value:</strong> {{ $order->currency->code ?? '' }} {{ number_format($order->total_value, 2) }}</div>
                <div class="col-md-3 mb-2"><strong>Attachment:</strong>
                    @if($order->attachment)<a href="{{ \Illuminate\Support\Facades\Storage::disk(config('merchandising-sfl.upload_disk'))->url($order->attachment) }}" target="_blank">View</a>@else - @endif
                </div>
                @if($order->remarks)
                    <div class="col-12 mb-2"><strong>Remarks:</strong> {!! nl2br(e($order->remarks)) !!}</div>
                @endif
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">PO Lines</h6>
                @if($canEditPos)
                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#poModalnew"><i class="fa-solid fa-plus"></i> Add PO Line</button>
                @endif
            </div>
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr>
                            <th>PO No</th><th>Style</th><th>Color</th>
                            @foreach($sizeColumns as $size)<th class="text-center">{{ $size->name }}</th>@endforeach
                            <th class="text-right">Qty</th><th class="text-right">Unit Price</th><th class="text-right">Value</th><th>PCD</th><th>Shipment</th><th>Ship Mode</th>
                            @if($canEditPos)<th class="text-right">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->pos as $po)
                            @php $qtyBySize = $po->sizes->pluck('qty', 'size_id'); @endphp
                            <tr>
                                <td>{{ $po->po_no }}</td>
                                <td><a href="{{ route('msfl.styles.show', $po->style_id) }}">{{ $po->style->style_no ?? '-' }}</a></td>
                                <td>{{ $po->color->name ?? '-' }}</td>
                                @foreach($sizeColumns as $size)<td class="text-center">{{ $qtyBySize->get($size->id, '') }}</td>@endforeach
                                <td class="text-right font-weight-bold">{{ number_format($po->po_qty) }}</td>
                                <td class="text-right">{{ number_format($po->unit_price, 4) }}</td>
                                <td class="text-right">{{ number_format($po->total_value, 2) }}</td>
                                <td>{{ optional($po->pcd_date)->format('d M Y') ?? '-' }}</td>
                                <td>{{ optional($po->shipment_date)->format('d M Y') ?? '-' }}</td>
                                <td>{{ $po->shipMode->name ?? '-' }}</td>
                                @if($canEditPos)
                                    <td class="text-right">
                                        <button type="button" class="btn-custom yellow" data-toggle="modal" data-target="#poModal{{ $po->id }}"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" class="btn-custom danger" data-toggle="modal" data-target="#deletePoModal" data-action="{{ route('msfl.orders.pos.destroy', [$order, $po]) }}"><i class="fa-solid fa-trash"></i></button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ 10 + $sizeColumns->count() }}" class="text-center text-muted">No PO lines yet.</td></tr>
                        @endforelse
                    </tbody>
                    @if($order->pos->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-right">Total</th>
                                @foreach($sizeColumns as $size)
                                    <th class="text-center">{{ $order->pos->sum(fn ($po) => $po->sizes->where('size_id', $size->id)->sum('qty')) }}</th>
                                @endforeach
                                <th class="text-right">{{ number_format($order->total_qty) }}</th><th></th><th class="text-right">{{ number_format($order->total_value, 2) }}</th>
                                <th colspan="{{ $canEditPos ? 4 : 3 }}"></th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Order BOM</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th>Style</th><th>Order Qty</th><th>BOM</th><th>Status</th><th>T&amp;A</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($order->pos->groupBy('style_id') as $styleId => $stylePos)
                            @php $styleBoms = $order->boms->where('style_id', $styleId); @endphp
                            <tr>
                                <td>{{ $stylePos->first()->style->label() }}</td>
                                <td>{{ number_format($stylePos->sum('po_qty')) }}</td>
                                <td>
                                    @forelse($styleBoms as $bom)
                                        <a href="{{ route('msfl.boms.show', $bom) }}">{{ $bom->bom_no }}</a> (v{{ $bom->version }})@if(! $loop->last), @endif
                                    @empty
                                        <span class="text-muted">No BOM</span>
                                    @endforelse
                                </td>
                                <td>
                                    @foreach($styleBoms as $bom)
                                        @include('merchandising-sfl::admin.partials.status-badge', ['model' => $bom])
                                    @endforeach
                                </td>
                                <td>
                                    @php $styleTna = $tnaPlans->get($styleId); @endphp
                                    @if($styleTna)
                                        <a href="{{ route('msfl.tna.show', $styleTna) }}">{{ $styleTna->tna_no }}</a>
                                    @elseif($order->status === 'confirmed')
                                        @can('msfl_tna.add')
                                            <a href="{{ route('msfl.tna.create', ['order_id' => $order->id]) }}" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-calendar-check"></i> Create T&amp;A</a>
                                        @endcan
                                    @else
                                        <span class="text-muted small">after confirmation</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @can('msfl_bom.add')
                                        <a href="{{ route('msfl.boms.create', ['style_id' => $styleId, 'order_id' => $order->id]) }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-plus"></i> Create BOM</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Add PO lines to build the order BOM.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if($canEditPos)
    @include('merchandising-sfl::admin.orders.partials.po-modal', ['po' => null])
    @foreach($order->pos as $po)
        @include('merchandising-sfl::admin.orders.partials.po-modal', ['po' => $po])
    @endforeach
    @include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deletePoModal', 'label' => 'PO line'])

    @push('js')
    <script>
        // Live size total per PO modal.
        $(document).on('input', '[data-po-size]', function () {
            const key = this.dataset.poSize;
            let total = 0;
            document.querySelectorAll('[data-po-size="' + key + '"]').forEach(el => total += parseInt(el.value) || 0);
            document.querySelector('[data-po-total="' + key + '"]').textContent = total.toLocaleString();
        });
        $(function () {
            $('[data-po-size]').trigger('input');
            @php $failedPoForm = old('_po_form'); @endphp
            @if($errors->any() && ($failedPoForm === 'new' || $order->pos->contains('id', (int) $failedPoForm)))
                $('#poModal' + @json($failedPoForm)).modal('show');
            @endif
        });
    </script>
    @endpush
@endif
@include('merchandising-sfl::admin.partials.select2-init')
@endsection

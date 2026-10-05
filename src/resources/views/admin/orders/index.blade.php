@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Orders') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Orders</h4>
            @can('msfl_order.add')
                <a href="{{ route('msfl.orders.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New Order</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Order / Buyer Ref / PO No" value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <select name="buyer_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Buyers</option>
                        @foreach($buyers as $buyer)
                            <option value="{{ $buyer->id }}" @selected(request('buyer_id') == $buyer->id)>{{ $buyer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        @foreach(\ME\MerchandisingSfl\Models\Order::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('msfl.orders.index') }}" class="btn btn-light btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>Order No</th><th>Date</th><th>Buyer</th><th>Buyer Ref</th><th>Season</th><th>PO Lines</th><th>Total Qty</th><th>Total Value</th><th>Merchandiser</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>{{ $loop->iteration + $orders->firstItem() - 1 }}</td>
                                <td>{{ $order->order_no }}</td>
                                <td>{{ $order->order_date->format('d M Y') }}</td>
                                <td>{{ $order->buyer->name ?? '-' }}</td>
                                <td>{{ $order->buyer_order_ref ?? '-' }}</td>
                                <td>{{ $order->season->name ?? '-' }}</td>
                                <td>{{ $order->pos_count }}</td>
                                <td>{{ number_format($order->total_qty) }}</td>
                                <td>{{ $order->currency->code ?? '' }} {{ number_format($order->total_value, 2) }}</td>
                                <td>{{ $order->merchandiser->name ?? '-' }}</td>
                                <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $order])</td>
                                <td class="text-right">
                                    @can('msfl_order.view')
                                        <a href="{{ route('msfl.orders.show', $order) }}" class="btn-custom success"><i class="fa-solid fa-eye"></i></a>
                                    @endcan
                                    @if($order->isEditable())
                                        @can('msfl_order.edit')
                                            <a href="{{ route('msfl.orders.edit', $order) }}" class="btn-custom yellow"><i class="fa-solid fa-pen"></i></a>
                                        @endcan
                                    @endif
                                    @if($order->status === 'draft')
                                        @can('msfl_order.delete')
                                            <button type="button" class="btn-custom danger" data-toggle="modal" data-target="#deleteOrderModal" data-action="{{ route('msfl.orders.destroy', $order) }}"><i class="fa-solid fa-trash"></i></button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="text-center text-muted">No orders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $orders->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteOrderModal', 'label' => 'order'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection

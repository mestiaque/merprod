@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Fabric Requisition') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Fabric Requisition</h4>
            @if($available)
                @can('msfl_prod_requisition.add')
                    <a href="{{ route('msfl.production.requisitions.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New Requisition</a>
                @endcan
            @endif
        </div>
        <div class="card-body">
            <p class="small text-muted">Sent to the Inventory store as a normal requisition — the store approves and issues it there; issued quantities show here.</p>
            <form method="GET" class="row mb-3 align-items-end">
                @include('merchandising-sfl::admin.production.partials.po-select', ['filter' => true, 'col' => 6])
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('msfl.production.requisitions.index') }}" class="btn btn-light btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th>Requisition</th><th>Date</th><th>Order / PO</th><th>Style · Color</th><th>Store</th><th>Status</th><th>Items (requested / issued)</th><th>By</th></tr></thead>
                    <tbody>
                        @forelse($requisitions as $link)
                            @php $req = $link->requisition; @endphp
                            <tr>
                                <td>{{ $req->requisition_no ?? '(deleted in Inventory)' }}</td>
                                <td>{{ $req?->requisition_date?->format('d-M-Y') ?? '-' }}</td>
                                <td><a href="{{ route('msfl.production.status.show', $link->order_po_id) }}">{{ $link->orderPo->order->order_no ?? '' }} · {{ $link->orderPo->po_no ?? '' }}</a></td>
                                <td>{{ $link->orderPo->style->style_no ?? '' }} · {{ $link->orderPo->color->name ?? '' }}</td>
                                <td>{{ $req->store->name ?? '-' }}</td>
                                <td>@if($req)<span class="badge badge-light border">{{ ucfirst(str_replace('_', ' ', $req->status)) }}</span>@endif</td>
                                <td class="small">
                                    @foreach($req?->items ?? [] as $item)
                                        <div>{{ $item->item->item_name ?? '-' }}: {{ (float) $item->requested_qty }} / <strong>{{ (float) $item->issued_qty }}</strong></div>
                                    @endforeach
                                </td>
                                <td>{{ $link->creator->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">No requisition yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $requisitions->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection

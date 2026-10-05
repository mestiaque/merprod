{{-- PO lines of an order: table + add / edit / delete modals. Used on order show and edit.
     props: order (pos.style/color/shipMode/sizes loaded), sizeColumns, formSizes, styles, colors, shipModes --}}
@php $canEditPos = $order->isEditable() && auth()->user()->can('msfl_order.edit'); @endphp
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

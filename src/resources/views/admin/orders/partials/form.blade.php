{{-- props: order (model, may be unsaved), buyers, seasons, merchandisers, factories, inquiries, currencies --}}
<div class="row">
    @include('merchandising-sfl::admin.partials.select', ['name' => 'buyer_id', 'label' => 'Buyer', 'required' => true, 'options' => $buyers->pluck('name', 'id'), 'value' => $order->buyer_id])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'order_date', 'label' => 'Order Date', 'type' => 'date', 'required' => true, 'value' => $order->order_date])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'buyer_order_ref', 'label' => 'Buyer Order / Contract Ref', 'value' => $order->buyer_order_ref])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'season_id', 'label' => 'Season', 'options' => $seasons->pluck('name', 'id'), 'value' => $order->season_id])

    @include('merchandising-sfl::admin.partials.select', ['name' => 'merchandiser_id', 'label' => 'Merchandiser', 'options' => $merchandisers->pluck('name', 'id'), 'value' => $order->merchandiser_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'factory_id', 'label' => 'Factory', 'options' => $factories->pluck('name', 'id'), 'value' => $order->factory_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'inquiry_id', 'label' => 'Inquiry', 'options' => $inquiries->mapWithKeys(fn ($i) => [$i->id => $i->inquiry_no . ($i->style_ref ? ' — ' . $i->style_ref : '')]), 'value' => $order->inquiry_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'currency_id', 'label' => 'Currency', 'options' => $currencies->pluck('code', 'id'), 'value' => $order->currency_id])

    @include('merchandising-sfl::admin.partials.select', ['name' => 'delivery_term', 'label' => 'Delivery Term', 'options' => \ME\MerchandisingSfl\Models\Order::DELIVERY_TERMS, 'value' => $order->delivery_term])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'payment_term', 'label' => 'Payment Term', 'value' => $order->payment_term])
    <div class="col-md-3 mb-3">
        <label class="form-label">Attachment (contract / PO sheet)</label>
        <input type="file" name="attachment" class="form-control form-control-sm">
        @if($order->attachment)
            <small><a href="{{ \Illuminate\Support\Facades\Storage::disk(config('merchandising-sfl.upload_disk'))->url($order->attachment) }}" target="_blank">Current file</a> — upload to replace</small>
        @endif
    </div>
    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'col' => 12, 'value' => $order->remarks])
</div>

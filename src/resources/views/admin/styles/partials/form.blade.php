{{-- props: style (nullable), buyers, inquiries, seasons, merchandisers, productTypes, washTypes --}}
<div class="row">
    @include('merchandising-sfl::admin.partials.input', ['name' => 'style_no', 'label' => 'Style No', 'required' => true, 'value' => $style->style_no ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'name', 'label' => 'Style Name', 'required' => true, 'value' => $style->name ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'buyer_id', 'label' => 'Buyer', 'required' => true, 'options' => $buyers->pluck('name', 'id'), 'value' => $style->buyer_id ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'inquiry_id', 'label' => 'Inquiry', 'options' => $inquiries->mapWithKeys(fn ($i) => [$i->id => $i->inquiry_no . ($i->style_ref ? ' — ' . $i->style_ref : '')]), 'value' => $style->inquiry_id ?? null])

    @include('merchandising-sfl::admin.partials.select', ['name' => 'season_id', 'label' => 'Season', 'options' => $seasons->pluck('name', 'id'), 'value' => $style->season_id ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'merchandiser_id', 'label' => 'Merchandiser', 'options' => $merchandisers->pluck('name', 'id'), 'value' => $style->merchandiser_id ?? auth()->id()])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'product_type_id', 'label' => 'Product Type', 'options' => $productTypes->pluck('name', 'id'), 'value' => $style->product_type_id ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'wash_type_id', 'label' => 'Wash Type', 'options' => $washTypes->pluck('name', 'id'), 'value' => $style->wash_type_id ?? null])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'smv', 'label' => 'SMV', 'type' => 'number', 'value' => $style->smv ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'target_cm', 'label' => 'Target CM (per dozen)', 'type' => 'number', 'value' => $style->target_cm ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'confirm_cm', 'label' => 'Confirm CM (per dozen)', 'type' => 'number', 'value' => $style->confirm_cm ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'fabric_sourced_by', 'label' => 'Fabric Sourced By', 'required' => true, 'options' => ['self' => 'Self (Factory)', 'buyer' => 'Buyer (Nominated / Supplied)'], 'value' => $style->fabric_sourced_by ?? 'self'])

    @include('merchandising-sfl::admin.partials.select', ['name' => 'development_status', 'label' => 'Development Status', 'required' => true, 'options' => \ME\MerchandisingSfl\Models\Style::statusOptions(), 'value' => $style->development_status ?? 'new'])
    @foreach(['tech_pack_file' => 'Tech Pack', 'artwork_file' => 'Artwork', 'size_chart_file' => 'Size Chart'] as $file => $fileLabel)
        <div class="col-md-3 mb-3">
            <label class="form-label">{{ $fileLabel }} File</label>
            <input type="file" name="{{ $file }}" class="form-control form-control-sm">
            @if($style?->{$file})
                <small><a href="{{ \Illuminate\Support\Facades\Storage::disk(config('merchandising-sfl.upload_disk'))->url($style->{$file}) }}" target="_blank">Current file</a> — upload to replace</small>
            @endif
        </div>
    @endforeach

    @include('merchandising-sfl::admin.partials.input', ['name' => 'fabric_description', 'label' => 'Fabric Description', 'type' => 'textarea', 'col' => 6, 'value' => $style->fabric_description ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'description', 'label' => 'Style Description / Construction', 'type' => 'textarea', 'col' => 6, 'value' => $style->description ?? null])

    <div class="col-md-3 mb-3 d-flex align-items-center"><div class="custom-control custom-switch">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="styleActive" @checked(old('is_active', $style->is_active ?? true))>
        <label class="custom-control-label" for="styleActive">Active</label>
    </div></div>
</div>

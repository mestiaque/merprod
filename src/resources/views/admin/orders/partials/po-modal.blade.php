{{-- props: order, po (nullable), formSizes, styles, colors, shipModes --}}
@php
    $formKey = $po->id ?? 'new';
    // old() only belongs to the modal that was submitted.
    $useOld = old('_po_form') === (string) $formKey;
    $val = fn ($field, $default = null) => $useOld ? old($field) : ($po->{$field} ?? $default);
    $sizeQty = $po ? $po->sizes->pluck('qty', 'size_id') : collect();
    $fieldId = fn ($field) => 'po' . $formKey . '_' . $field;
@endphp
<div class="modal fade" id="poModal{{ $formKey }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="{{ $po ? route('msfl.orders.pos.update', [$order, $po]) : route('msfl.orders.pos.store', $order) }}">
                @csrf
                @if($po) @method('PUT') @endif
                <input type="hidden" name="_po_form" value="{{ $formKey }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $po ? 'Edit PO Line — ' . $po->po_no : 'Add PO Line' }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">PO No <span class="text-danger">*</span></label>
                            <input type="text" name="po_no" class="form-control form-control-sm" value="{{ $val('po_no') }}" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Style <span class="text-danger">*</span></label>
                            <select name="style_id" class="form-control form-control-sm msfl-select2" required>
                                <option value="">— Select —</option>
                                @foreach($styles as $style)
                                    <option value="{{ $style->id }}" @selected((string) $val('style_id') === (string) $style->id)>{{ $style->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Color <span class="text-danger">*</span></label>
                            <select name="color_id" class="form-control form-control-sm msfl-select2" required>
                                <option value="">— Select —</option>
                                @foreach($colors as $color)
                                    <option value="{{ $color->id }}" @selected((string) $val('color_id') === (string) $color->id)>{{ $color->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Unit Price <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0" name="unit_price" class="form-control form-control-sm" value="{{ $val('unit_price') }}" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">PCD (Plan Cut Date)</label>
                            <input type="date" name="pcd_date" class="form-control form-control-sm" value="{{ $useOld ? old('pcd_date') : optional($po?->pcd_date)->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Shipment Date</label>
                            <input type="date" name="shipment_date" class="form-control form-control-sm" value="{{ $useOld ? old('shipment_date') : optional($po?->shipment_date)->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Ship Mode</label>
                            <select name="ship_mode_id" class="form-control form-control-sm msfl-select2">
                                <option value="">— Select —</option>
                                @foreach($shipModes as $mode)
                                    <option value="{{ $mode->id }}" @selected((string) $val('ship_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="remarks" class="form-control form-control-sm" value="{{ $val('remarks') }}">
                        </div>
                        {{-- Optional production stages for this PO. --}}
                        @foreach(['needs_embroidery' => 'Needs Embroidery', 'needs_washing' => 'Needs Washing', 'applique_ih' => 'EMB Applique IH', 'studs_stones_ih' => 'Studs / Stones IH', 'heat_seal_ih' => 'Heat Seal IH'] as $flag => $flagLabel)
                            <div class="col-md-3 mb-3 d-flex align-items-center">
                                <div class="custom-control custom-switch">
                                    <input type="hidden" name="{{ $flag }}" value="0">
                                    <input type="checkbox" name="{{ $flag }}" value="1" class="custom-control-input" id="{{ $fieldId($flag) }}" @checked((bool) $val($flag, false))>
                                    <label class="custom-control-label" for="{{ $fieldId($flag) }}">{{ $flagLabel }} <small class="text-muted">(production)</small></label>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <h6>Size Breakdown (pcs) <small class="text-muted">— total: <span data-po-total="{{ $formKey }}">0</span></small></h6>
                    @if($formSizes->isEmpty())
                        <div class="alert alert-warning mb-0">No active sizes — add them in Inventory → Sizes.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead><tr>@foreach($formSizes as $size)<th class="text-center">{{ $size->name }}</th>@endforeach</tr></thead>
                                <tbody><tr>
                                    @foreach($formSizes as $size)
                                        <td style="min-width:70px">
                                            <input type="number" min="0" step="1" name="sizes[{{ $size->id }}]" class="form-control form-control-sm text-right" data-po-size="{{ $formKey }}"
                                                value="{{ $useOld ? old('sizes.' . $size->id) : $sizeQty->get($size->id) }}">
                                        </td>
                                    @endforeach
                                </tr></tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">{{ $po ? 'Update' : 'Save' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- props: pos, selected (id|null), required (bool, default true), name (default order_po_id), col (default 6), filter (bool: no label, "All POs") --}}
@php $name = $name ?? 'order_po_id'; $filter = $filter ?? false; @endphp
<div class="col-md-{{ $col ?? 6 }} mb-{{ $filter ? 2 : 3 }}">
    @unless($filter)<label class="form-label">Order / PO <span class="text-danger">*</span> <small class="text-muted">(buyer, style, color come from it)</small></label>@endunless
    <select name="{{ $name }}" id="poSelect" class="form-control form-control-sm msfl-select2" @required(! $filter && ($required ?? true))>
        <option value="">{{ $filter ? 'All POs' : '— Select order PO —' }}</option>
        @foreach($pos as $po)
            <option value="{{ $po->id }}" @selected((string) old($name, $selected ?? request($name)) === (string) $po->id)>{{ $po->label() }} — {{ $po->order->buyer->name ?? '' }} ({{ number_format($po->po_qty) }} pcs)</option>
        @endforeach
    </select>
</div>

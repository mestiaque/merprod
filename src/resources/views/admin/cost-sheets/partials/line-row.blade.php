{{-- props: index (int|"__INDEX__"), group, line (array), itemOptions, uoms --}}
<tr data-cs-row>
    <td style="min-width:200px">
        <input type="hidden" name="items[{{ $index }}][group]" value="{{ $group }}">
        <select name="items[{{ $index }}][item_id]" class="form-control form-control-sm msfl-select2" data-cs-item>
            <option value="">— Item —</option>
            @foreach($itemOptions as $item)
                <option value="{{ $item->id }}" data-uom="{{ $item->uom_id }}" data-rate="{{ $item->default_price }}"
                    @selected(($line['item_id'] ?? null) == $item->id)>{{ $item->name }} ({{ $item->code }})</option>
            @endforeach
        </select>
    </td>
    <td><input type="text" name="items[{{ $index }}][description]" class="form-control form-control-sm" value="{{ $line['description'] ?? '' }}" placeholder="Description / supplier"></td>
    <td style="min-width:100px">
        <select name="items[{{ $index }}][uom_id]" class="form-control form-control-sm" data-cs-uom>
            <option value=""></option>
            @foreach($uoms as $uom)
                <option value="{{ $uom->id }}" @selected(($line['uom_id'] ?? null) == $uom->id)>{{ $uom->code }}</option>
            @endforeach
        </select>
    </td>
    <td style="width:120px"><input type="number" step="any" min="0" name="items[{{ $index }}][consumption]" class="form-control form-control-sm text-right" value="{{ $line['consumption'] ?? '' }}" data-cs-cons></td>
    <td style="width:120px"><input type="number" step="any" min="0" name="items[{{ $index }}][rate]" class="form-control form-control-sm text-right" value="{{ $line['rate'] ?? '' }}" data-cs-rate></td>
    <td style="width:120px"><input type="text" class="form-control form-control-sm text-right" readonly data-cs-amount></td>
    <td><input type="text" name="items[{{ $index }}][remarks]" class="form-control form-control-sm" value="{{ $line['remarks'] ?? '' }}"></td>
    <td style="width:40px"><button type="button" class="btn-custom danger" data-line-items-remove><i class="fa-solid fa-xmark"></i></button></td>
</tr>

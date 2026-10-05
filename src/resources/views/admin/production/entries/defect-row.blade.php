{{-- props: index, d (old values), usesLine, machines --}}
<tr>
    <td>
        <select name="defects[{{ $index }}][type]" class="form-control form-control-sm" data-defect-type>
            <option value="reject" @selected(($d['type'] ?? 'reject') === 'reject')>Reject</option>
            <option value="rework" @selected(($d['type'] ?? '') === 'rework')>Rework</option>
        </select>
    </td>
    <td><input type="text" name="defects[{{ $index }}][part_name]" class="form-control form-control-sm" value="{{ $d['part_name'] ?? '' }}" placeholder="e.g. Collar, Sleeve"></td>
    @if($usesLine)
        <td>
            <select name="defects[{{ $index }}][machine_id]" class="form-control form-control-sm" data-machine-select>
                <option value="">— Machine —</option>
                @foreach($machines as $m)
                    <option value="{{ $m->id }}" data-line="{{ strtolower(trim((string) $m->line)) }}" @selected(($d['machine_id'] ?? null) == $m->id)>{{ $m->code }} — {{ $m->name }}{{ $m->type ? ' (' . $m->type . ')' : '' }}</option>
                @endforeach
            </select>
        </td>
    @endif
    <td><input type="text" name="defects[{{ $index }}][defect]" class="form-control form-control-sm" value="{{ $d['defect'] ?? '' }}" placeholder="e.g. Broken stitch, Oil spot"></td>
    <td><input type="number" min="0" step="1" name="defects[{{ $index }}][qty]" class="form-control form-control-sm text-right" value="{{ $d['qty'] ?? '' }}" data-defect-qty></td>
    <td><button type="button" class="btn-custom danger" data-line-items-remove><i class="fa-solid fa-xmark"></i></button></td>
</tr>

{{-- props: entries, showStage (bool), showPo (bool), deleteRoute (optional: route name taking the entry; default the stage entry route) --}}
@php use ME\MerchandisingSfl\Services\ProductionFlow; @endphp
<table class="table table-bordered table-sm align-middle mb-0">
    <thead>
        <tr>
            <th>Date</th>
            @if($showStage)<th>Stage</th>@endif
            @if($showPo)<th>Order / PO</th><th>Style · Color</th>@endif
            <th>Size</th><th>Line</th>
            <th class="text-right">In</th><th class="text-right">Pass</th><th class="text-right">Rework</th><th class="text-right">Reject</th>
            <th>Defects (part · machine · defect · pcs)</th><th>Remarks</th><th>By</th>
            @can('msfl_prod_entry.delete')<th class="text-right">Actions</th>@endcan
        </tr>
    </thead>
    <tbody>
        @forelse($entries as $entry)
            <tr>
                <td>{{ $entry->entry_date->format('d-M-Y') }}
                    @if($entry->kind === 'qc')<br><span class="badge badge-danger">Reject / Rework found</span>
                    @elseif($entry->kind === 'rework')<br><span class="badge badge-success">Rework fixed</span>@endif
                </td>
                @if($showStage)<td>{{ ProductionFlow::label($entry->stage) }}</td>@endif
                @if($showPo)
                    <td><a href="{{ route('msfl.production.status.show', $entry->order_po_id) }}">{{ $entry->orderPo->order->order_no ?? '' }} · {{ $entry->orderPo->po_no ?? '' }}</a></td>
                    <td>{{ $entry->orderPo->style->style_no ?? '' }} · {{ $entry->orderPo->color->name ?? '' }}</td>
                @endif
                <td>{{ $entry->size->name ?? 'All' }}</td>
                <td>{{ $entry->line->name ?? '-' }}</td>
                <td class="text-right">{{ $entry->input_qty }}</td>
                <td class="text-right"><strong>{{ $entry->pass_qty }}</strong></td>
                <td class="text-right">{{ $entry->rework_qty }}</td>
                <td class="text-right {{ $entry->reject_qty ? 'text-danger' : '' }}">{{ $entry->reject_qty }}</td>
                <td class="small">
                    @foreach($entry->defects as $d)
                        <div><span class="badge badge-{{ $d->type === 'reject' ? 'danger' : 'warning' }}">{{ ucfirst($d->type) }}</span>
                            {{ collect([$d->part_name, $d->machineLabel(), $d->defect])->filter()->implode(' · ') }} · {{ $d->qty }}</div>
                    @endforeach
                </td>
                <td>{{ $entry->remarks }}</td>
                <td>{{ $entry->creator->name ?? '-' }}</td>
                @can('msfl_prod_entry.delete')
                    <td class="text-right">
                        <button type="button" class="btn-custom danger" data-toggle="modal" data-target="#deleteEntryModal"
                            data-action="{{ isset($deleteRoute) || $entry->stage === 'cutting' ? route($deleteRoute ?? 'msfl.production.reject-rework.destroy', $entry) : route('msfl.production.entries.destroy', ['stage' => $entry->stage, 'entry' => $entry]) }}"><i class="fa-solid fa-trash"></i></button>
                    </td>
                @endcan
            </tr>
        @empty
            <tr><td colspan="14" class="text-center text-muted">No entries yet.</td></tr>
        @endforelse
    </tbody>
</table>
@can('msfl_prod_entry.delete')
    @include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteEntryModal', 'label' => 'entry'])
@endcan

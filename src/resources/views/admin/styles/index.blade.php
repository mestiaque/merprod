@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Tech Pack / Styles') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Tech Pack / Styles</h4>
            @can('msfl_style.add')
                <a href="{{ route('msfl.styles.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New Style</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Style No / Name" value="{{ request('search') }}">
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
                        @foreach(\ME\MerchandisingSfl\Models\Style::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('msfl.styles.index') }}" class="btn btn-light btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>Style No</th><th>Name</th><th>Buyer</th><th>Season</th><th>Product Type</th><th>SMV</th><th>Confirm CM</th><th>Merchandiser</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($styles as $style)
                            <tr>
                                <td>{{ $loop->iteration + $styles->firstItem() - 1 }}</td>
                                <td>{{ $style->style_no }}</td>
                                <td>{{ $style->name }}</td>
                                <td>{{ $style->buyer->name ?? '-' }}</td>
                                <td>{{ $style->season->name ?? '-' }}</td>
                                <td>{{ $style->productType->name ?? '-' }}</td>
                                <td>{{ $style->smv ?? '-' }}</td>
                                <td>{{ $style->confirm_cm ?? '-' }}</td>
                                <td>{{ $style->merchandiser->name ?? '-' }}</td>
                                <td>
                                    @include('merchandising-sfl::admin.partials.status-badge', ['model' => $style])
                                    @unless($style->is_active)<span class="badge badge-secondary">Inactive</span>@endunless
                                </td>
                                <td class="text-right">
                                    @can('msfl_style.view')
                                        <a href="{{ route('msfl.styles.show', $style) }}" class="btn-custom success"><i class="fa-solid fa-eye"></i></a>
                                    @endcan
                                    @can('msfl_style.edit')
                                        <a href="{{ route('msfl.styles.edit', $style) }}" class="btn-custom yellow"><i class="fa-solid fa-pen"></i></a>
                                    @endcan
                                    @can('msfl_style.delete')
                                        <button type="button" class="btn-custom danger" data-toggle="modal" data-target="#deleteStyleModal" data-action="{{ route('msfl.styles.destroy', $style) }}"><i class="fa-solid fa-trash"></i></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted">No styles found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $styles->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteStyleModal', 'label' => 'style'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection

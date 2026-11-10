{{-- props: result — one table, or several under 'sections' (each with a title); optional 'summary' (label => value tiles) --}}
@if(! empty($result['summary']))
    <div class="d-flex flex-wrap mb-2 report-summary" style="gap:6px">
        @foreach($result['summary'] as $label => $value)
            <div style="display:flex;border:1px solid #1f4e79;border-radius:4px;overflow:hidden;font-size:12px">
                <span style="background:#1f4e79;color:#fff;font-weight:700;padding:4px 10px">{{ $label }}</span>
                <span style="background:#dbeaf7;font-weight:700;padding:4px 14px;min-width:48px;text-align:center">{{ $value }}</span>
            </div>
        @endforeach
    </div>
@endif
@if(isset($result['sections']))
    @foreach($result['sections'] as $section)
        <h6 class="report-section-title {{ $loop->first ? '' : 'mt-3' }}">{{ $section['title'] }}</h6>
        <div class="table-responsive">@include('merchandising-sfl::admin.reports.partials.table', ['result' => $section])</div>
    @endforeach
@else
    <div class="table-responsive">@include('merchandising-sfl::admin.reports.partials.table', ['result' => $result])</div>
@endif

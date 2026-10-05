{{-- "এই পাতা কী কাজে" — help box from Support/PageHelp, keyed by the current route. Closed by default; only for users with msfl_page_help.view. --}}
@php
    $pageHelp = \ME\MerchandisingSfl\Support\PageHelp::current();
@endphp
@if($pageHelp && auth()->user()?->can('msfl_page_help.view'))
    <style>
        .msfl-help { border-left: 4px solid #17a2b8; background: #f3fbfd; font-size: .875rem; }
        .msfl-help > summary { cursor: pointer; padding: .5rem .75rem; font-weight: 600; color: #0c5460; list-style: none; }
        .msfl-help > summary::-webkit-details-marker { display: none; }
        .msfl-help > summary .msfl-help-toggle { float: right; font-weight: normal; color: #6c757d; }
        .msfl-help[open] > summary .msfl-help-toggle .when-closed, .msfl-help:not([open]) > summary .msfl-help-toggle .when-open { display: none; }
        .msfl-help .msfl-help-body { padding: 0 .75rem .75rem; }
        .msfl-help ol { padding-left: 1.25rem; margin-bottom: .5rem; }
        .msfl-help .msfl-help-flow span { display: inline-block; padding: 0 .4rem; border-radius: 3px; background: #e2e3e5; }
        .msfl-help .msfl-help-flow .current { background: #17a2b8; color: #fff; }
    </style>
    <details class="msfl-help card mb-3" data-help-key="{{ $pageHelp['key'] }}">
        <summary>
            <i class="fa-solid fa-circle-info"></i> এই পাতা কী কাজে — {{ $pageHelp['title'] }}
            <span class="msfl-help-toggle"><span class="when-open">লুকান ▲</span><span class="when-closed">দেখুন ▼</span></span>
        </summary>
        <div class="msfl-help-body">
            <p class="mb-2">{!! $pageHelp['what'] !!}</p>

            <div class="mb-1"><strong>কীভাবে ব্যবহার করবেন</strong></div>
            <ol>
                @foreach($pageHelp['steps'] as $step)
                    <li>{!! $step !!}</li>
                @endforeach
            </ol>

            @if($pageHelp['tip'])
                <div class="alert alert-warning py-1 px-2 mb-2"><i class="fa-solid fa-lightbulb"></i> {!! $pageHelp['tip'] !!}</div>
            @endif

            @if(! empty($pageHelp['example']))
                <div class="mb-2"><strong>উদাহরণ:</strong> {!! $pageHelp['example'] !!}</div>
            @endif

            <div class="msfl-help-flow">
                <strong>Process-এ কোথায়:</strong>
                <span>{{ $pageHelp['before'] }}</span> →
                <span class="current">{{ $pageHelp['title'] }}</span> →
                <span>{{ $pageHelp['after'] }}</span>
            </div>
        </div>
    </details>
@endif

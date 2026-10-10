<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use ME\MerchandisingSfl\Services\ProductionFlow;
use ME\MerchandisingSfl\Services\Reports;
use ME\MerchandisingSfl\Support\Lookups;

/** Reports: list, one report on screen (with filters), and its print. */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('msfl_report.view');

        // Only the reports this user may open (commercial ones have their own permission).
        $reports = array_filter(Reports::LIST, fn ($r) => (bool) $request->user()?->can(($r[4] ?? 'msfl_report') . '.view'));

        return view('merchandising-sfl::admin.reports.index', ['reports' => $reports]);
    }

    public function show(Request $request, string $report, Reports $reports): View
    {
        $this->authorize((Reports::LIST[$report][4] ?? 'msfl_report') . '.view');

        return view('merchandising-sfl::admin.reports.show', $this->data($request, $report, $reports));
    }

    public function print(Request $request, string $report, Reports $reports): View
    {
        $this->authorize((Reports::LIST[$report][4] ?? 'msfl_report') . '.view');

        return view('merchandising-sfl::admin.reports.print', $this->data($request, $report, $reports));
    }

    private function data(Request $request, string $report, Reports $reports): array
    {
        [$title, $icon, $filters, $description] = array_slice(Reports::LIST[$report], 0, 4);

        return [
            'key' => $report, 'title' => $title, 'description' => $description, 'filters' => $filters,
            'result' => $reports->run($report, $request),
            'buyers' => in_array('buyer', $filters, true) ? Lookups::buyers() : collect(),
            'lines' => in_array('line', $filters, true) ? Lookups::lines() : collect(),
            'styles' => in_array('style', $filters, true) ? Lookups::styles() : collect(),
            'stages' => ['cutting' => 'Cutting'] + collect(ProductionFlow::STAGES)->map(fn ($s) => $s[0])->all(),
        ];
    }
}

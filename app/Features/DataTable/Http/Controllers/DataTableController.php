<?php

namespace App\Features\DataTable\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MonitoredSystem;
use App\Models\Report;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DataTableController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $systems = MonitoredSystem::query()
            ->where('team_id', $current_team->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order']);

        $existingReportDates = Report::query()
            ->where('team_id', $current_team->id)
            ->pluck('date_reference')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();

        return Inertia::render('DataTable/Index', [
            'systems' => $systems,
            'currentTeam' => $current_team,
            'defaultStartDate' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'defaultEndDate' => Carbon::now()->format('Y-m-d'),
            'canManageReports' => (bool) $request->user()->is_admin,
            'existingReportDates' => $existingReportDates,
        ]);
    }
}

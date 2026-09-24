<?php

namespace App\Features\Dashboards\Http\Controllers;

use App\Features\Dashboards\Http\Requests\StoreDashboardRequest;
use App\Features\Dashboards\Http\Requests\UpdateDashboardRequest;
use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display a listing of dashboards and render active dashboard container.
     */
    public function index(Request $request, Team $current_team): Response
    {
        $user = $request->user();

        $dashboards = Dashboard::query()
            ->forTeamAndUser($current_team->id, $user->id)
            ->latest()
            ->get();

        $dashboardId = $request->query('dashboard_id');
        $activeDashboard = $dashboardId
            ? $dashboards->firstWhere('id', (int) $dashboardId)
            : $dashboards->first();

        if ($activeDashboard) {
            $activeDashboard->load(['components' => function ($query) {
                $query->select(['id', 'dashboard_id', 'type', 'grid_config', 'settings', 'created_at']);
            }]);
        }

        return Inertia::render('Dashboard', [
            'dashboards' => $dashboards,
            'activeDashboard' => $activeDashboard,
        ]);
    }

    /**
     * Display a specific dashboard container.
     */
    public function show(Request $request, Team $current_team, Dashboard $dashboard): Response|RedirectResponse
    {
        $user = $request->user();

        if ($dashboard->team_id !== $current_team->id || (! $dashboard->is_public && $dashboard->user_id !== $user->id)) {
            abort(403);
        }

        $dashboards = Dashboard::query()
            ->forTeamAndUser($current_team->id, $user->id)
            ->latest()
            ->get();

        $dashboard->load(['components' => function ($query) {
            $query->select(['id', 'dashboard_id', 'type', 'grid_config', 'settings', 'created_at']);
        }]);

        return Inertia::render('Dashboard', [
            'dashboards' => $dashboards,
            'activeDashboard' => $dashboard,
        ]);
    }

    /**
     * Store a newly created dashboard.
     */
    public function store(StoreDashboardRequest $request, Team $current_team): RedirectResponse
    {
        $validated = $request->validated();

        $dashboard = Dashboard::create([
            'title' => $validated['title'],
            'user_id' => $request->user()->id,
            'team_id' => $current_team->id,
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return redirect()->route('dashboards.show', [
            'current_team' => $current_team->slug,
            'dashboard' => $dashboard->id,
        ]);
    }

    /**
     * Update the specified dashboard.
     */
    public function update(UpdateDashboardRequest $request, Team $current_team, Dashboard $dashboard): RedirectResponse
    {
        $user = $request->user();

        if ($dashboard->team_id !== $current_team->id || $dashboard->user_id !== $user->id) {
            abort(403);
        }

        $dashboard->update($request->validated());

        return back();
    }

    /**
     * Remove the specified dashboard.
     */
    public function destroy(Request $request, Team $current_team, Dashboard $dashboard): RedirectResponse
    {
        $user = $request->user();

        if ($dashboard->team_id !== $current_team->id || $dashboard->user_id !== $user->id) {
            abort(403);
        }

        $dashboard->delete();

        return redirect()->route('dashboards.index', ['current_team' => $current_team->slug]);
    }
}

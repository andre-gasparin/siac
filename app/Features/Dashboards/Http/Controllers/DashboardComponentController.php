<?php

namespace App\Features\Dashboards\Http\Controllers;

use App\Features\Dashboards\Http\Requests\StoreDashboardComponentRequest;
use App\Features\Dashboards\Http\Requests\UpdateDashboardComponentRequest;
use App\Features\Dashboards\Http\Requests\UpdateDashboardGridRequest;
use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Models\DashboardComponent;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardComponentController extends Controller
{
    /**
     * Add a new component to the dashboard.
     */
    public function store(StoreDashboardComponentRequest $request, Team $current_team, Dashboard $dashboard): JsonResponse
    {
        $user = $request->user();

        if ($dashboard->team_id !== $current_team->id || (! $dashboard->is_public && $dashboard->user_id !== $user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();

        $component = $dashboard->components()->create([
            'type' => $validated['type'],
            'grid_config' => $validated['grid_config'],
            'settings' => $validated['settings'] ?? [],
        ]);

        return response()->json($component, 201);
    }

    /**
     * Update component settings via AJAX modal save.
     */
    public function update(
        UpdateDashboardComponentRequest $request,
        Team $current_team,
        Dashboard $dashboard,
        DashboardComponent $component
    ): JsonResponse {
        $user = $request->user();

        if ($dashboard->team_id !== $current_team->id || $component->dashboard_id !== $dashboard->id || (! $dashboard->is_public && $dashboard->user_id !== $user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();

        $component->update(array_filter([
            'settings' => $validated['settings'],
            'grid_config' => $validated['grid_config'] ?? null,
        ]));

        return response()->json($component);
    }

    /**
     * Batch update grid layout positions (drag & drop / resize) via AJAX autosave.
     */
    public function updateGrid(UpdateDashboardGridRequest $request, Team $current_team, Dashboard $dashboard): JsonResponse
    {
        $user = $request->user();

        if ($dashboard->team_id !== $current_team->id || (! $dashboard->is_public && $dashboard->user_id !== $user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();

        foreach ($validated['components'] as $item) {
            $dashboard->components()
                ->where('id', $item['id'])
                ->update(['grid_config' => json_encode($item['grid_config'])]);
        }

        return response()->json(['success' => true, 'message' => 'Grid configuration updated successfully']);
    }

    /**
     * Delete a component from the dashboard.
     */
    public function destroy(
        Request $request,
        Team $current_team,
        Dashboard $dashboard,
        DashboardComponent $component
    ): JsonResponse {
        $user = $request->user();

        if ($dashboard->team_id !== $current_team->id || $component->dashboard_id !== $dashboard->id || (! $dashboard->is_public && $dashboard->user_id !== $user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $component->delete();

        return response()->json(['success' => true]);
    }
}

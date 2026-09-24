<?php

namespace App\Features\Teams\Http\Controllers;

use App\Features\Teams\Actions\UpdateTeamSystems;
use App\Features\Teams\Http\Requests\UpdateTeamSystemsRequest;
use App\Http\Controllers\Controller;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamSystemController extends Controller
{
    /**
     * Show the team systems and parameters edit page.
     */
    public function edit(Request $request, Team $team): Response
    {
        Gate::authorize('update', $team);

        $monitoredSystems = $team->monitoredSystems()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['parameters' => function ($query) {
                $query->orderBy('sort_order')->orderBy('name');
            }])
            ->get()
            ->map(function (MonitoredSystem $system): array {
                return [
                    'id' => $system->id,
                    'name' => $system->name,
                    'is_active' => (bool) $system->is_active,
                    'sort_order' => $system->sort_order,
                    'parameters' => $system->parameters->map(function (Parameter $parameter): array {
                        return [
                            'id' => $parameter->id,
                            'name' => $parameter->name,
                            'code' => $parameter->code,
                            'tag' => $parameter->tag,
                            'unit' => $parameter->unit,
                            'decimals' => $parameter->decimals,
                            'sort_order' => $parameter->sort_order,
                            'is_active' => (bool) $parameter->is_active,
                            'alert_1_min' => $parameter->alert_1_min,
                            'alert_1_max' => $parameter->alert_1_max,
                            'alert_2_min' => $parameter->alert_2_min,
                            'alert_2_max' => $parameter->alert_2_max,
                            'alert_3_min' => $parameter->alert_3_min,
                            'alert_3_max' => $parameter->alert_3_max,
                            'alert_4_min' => $parameter->alert_4_min,
                            'alert_4_max' => $parameter->alert_4_max,
                        ];
                    })->all(),
                ];
            });

        return Inertia::render('teams/Systems', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
            ],
            'monitoredSystems' => $monitoredSystems,
        ]);
    }

    /**
     * Update the team systems and parameters.
     */
    public function update(
        UpdateTeamSystemsRequest $request,
        Team $team,
        UpdateTeamSystems $updateTeamSystems,
    ): RedirectResponse {
        Gate::authorize('update', $team);

        $updateTeamSystems->handle($team, $request->validated('systems'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sistemas e parâmetros atualizados com sucesso.')]);

        return back();
    }
}

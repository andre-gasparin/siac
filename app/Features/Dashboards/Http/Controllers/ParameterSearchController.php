<?php

namespace App\Features\Dashboards\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Parameter;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParameterSearchController extends Controller
{
    /**
     * Search parameters by individual word tokens across system name, parameter name, code, or tag.
     */
    public function __invoke(Request $request, Team $current_team): JsonResponse
    {
        if (! $request->user()->belongsToTeam($current_team) && ! $request->user()->isCurrentTeam($current_team)) {
            return response()->json(['message' => 'Unauthorized team access'], 403);
        }

        $rawQuery = trim((string) $request->query('q', ''));
        // Split query into individual words (ignoring empty spaces)
        $words = array_values(array_filter(explode(' ', $rawQuery), fn ($w) => mb_strlen($w) > 0));

        $parameters = Parameter::query()
            ->join('monitored_systems', 'monitored_systems.id', '=', 'parameters.monitored_system_id')
            ->with(['monitoredSystem:id,name,sort_order'])
            ->where('parameters.team_id', $current_team->id)
            ->where('parameters.is_active', true)
            ->where('monitored_systems.team_id', $current_team->id)
            ->where('monitored_systems.is_active', true)
            ->whereNull('monitored_systems.deleted_at')
            ->when(! empty($words), function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->where(function ($sub) use ($word) {
                        $sub->where('parameters.name', 'like', "%{$word}%")
                            ->orWhere('parameters.code', 'like', "%{$word}%")
                            ->orWhere('parameters.tag', 'like', "%{$word}%")
                            ->orWhere('monitored_systems.name', 'like', "%{$word}%");
                    });
                }
            })
            ->orderBy('monitored_systems.sort_order')
            ->orderBy('monitored_systems.name')
            ->orderBy('parameters.sort_order')
            ->orderBy('parameters.name')
            ->select('parameters.*')
            ->get();

        $results = $parameters->map(function (Parameter $param): array {
            $systemName = $param->monitoredSystem->name ?? '';
            $displayName = "{$systemName} - {$param->name}";

            return [
                'id' => $param->id,
                'monitored_system_id' => $param->monitored_system_id,
                'name' => $param->name,
                'system_name' => $systemName,
                'display_name' => $displayName,
                'code' => $param->code,
                'tag' => $param->tag,
                'unit' => $param->unit,
                'decimals' => $param->decimals,
                'alert_1_min' => $param->alert_1_min,
                'alert_1_max' => $param->alert_1_max,
            ];
        });

        return response()->json($results);
    }
}

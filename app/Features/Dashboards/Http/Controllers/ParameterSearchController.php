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
            ->with(['monitoredSystem:id,name'])
            ->where('team_id', $current_team->id)
            ->where('is_active', true)
            ->when(! empty($words), function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->where(function ($sub) use ($word) {
                        $sub->where('name', 'like', "%{$word}%")
                            ->orWhere('code', 'like', "%{$word}%")
                            ->orWhere('tag', 'like', "%{$word}%")
                            ->orWhereHas('monitoredSystem', function ($sysQuery) use ($word) {
                                $sysQuery->where('name', 'like', "%{$word}%");
                            });
                    });
                }
            })
            ->limit(30)
            ->get();

        $results = $parameters->map(function (Parameter $param) {
            $systemName = $param->monitoredSystem->name;
            $displayName = "{$systemName} - {$param->name}";

            return [
                'id' => $param->id,
                'name' => $param->name,
                'system_name' => $systemName,
                'display_name' => $displayName,
                'code' => $param->code,
                'tag' => $param->tag,
                'unit' => $param->unit,
                'decimals' => $param->decimals,
            ];
        });

        return response()->json($results);
    }
}

<?php

namespace App\Features\DataEntry\Actions;

use App\Models\ParameterValue;
use App\Models\ParameterValuesComment;
use App\Models\Team;
use Illuminate\Support\Carbon;

class FetchDataEntryEntriesAction
{
    /**
     * @return array{
     *     systems_with_data: list<int>,
     *     values: array<int, float>,
     *     comments: array<int, string>,
     *     collected_at: string
     * }
     */
    public function execute(Team $team, string $collectedAt): array
    {
        $measuredAt = Carbon::parse($collectedAt);

        $parameterValues = ParameterValue::query()
            ->where('team_id', $team->id)
            ->where('measured_at', $measuredAt)
            ->get(['monitored_system_id', 'parameter_id', 'value']);

        $parameterComments = ParameterValuesComment::query()
            ->where('team_id', $team->id)
            ->where('measured_at', $measuredAt)
            ->get(['monitored_system_id', 'comment']);

        $systemsWithValues = $parameterValues->pluck('monitored_system_id')->unique()->all();
        $systemsWithComments = $parameterComments->pluck('monitored_system_id')->unique()->all();

        /** @var list<int> $systemsWithData */
        $systemsWithData = array_values(array_unique(array_merge($systemsWithValues, $systemsWithComments)));

        /** @var array<int, float> $valuesMap */
        $valuesMap = [];
        foreach ($parameterValues as $pv) {
            if ($pv->value !== null) {
                $valuesMap[(int) $pv->parameter_id] = (float) $pv->value;
            }
        }

        /** @var array<int, string> $commentsMap */
        $commentsMap = [];
        foreach ($parameterComments as $pc) {
            if ($pc->comment !== null && $pc->comment !== '') {
                $commentsMap[(int) $pc->monitored_system_id] = (string) $pc->comment;
            }
        }

        return [
            'systems_with_data' => $systemsWithData,
            'values' => $valuesMap,
            'comments' => $commentsMap,
            'collected_at' => $measuredAt->format('Y-m-d H:i:s'),
        ];
    }
}

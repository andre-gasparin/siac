<?php

namespace App\Features\Teams\Actions;

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class UpdateTeamSystems
{
    /**
     * @param  list<array<string, mixed>>  $systems
     */
    public function handle(Team $team, array $systems): void
    {
        DB::transaction(function () use ($systems, $team): void {
            foreach ($systems as $systemData) {
                $system = MonitoredSystem::query()
                    ->whereBelongsTo($team)
                    ->whereKey($systemData['id'])
                    ->firstOrFail();
                $system->update([
                    'name' => $systemData['name'],
                    'is_active' => $systemData['is_active'],
                    'sort_order' => $systemData['sort_order'],
                ]);

                foreach ($systemData['parameters'] ?? [] as $parameterData) {
                    $parameter = Parameter::query()
                        ->whereBelongsTo($team)
                        ->whereBelongsTo($system, 'monitoredSystem')
                        ->whereKey($parameterData['id'])
                        ->firstOrFail();

                    $parameter->update([
                        'name' => $parameterData['name'],
                        'code' => $parameterData['code'],
                        'tag' => $parameterData['tag'],
                        'category' => $this->nullableValue($parameterData['category'] ?? null),
                        'unit' => $parameterData['unit'],
                        'decimals' => $parameterData['decimals'],
                        'sort_order' => $parameterData['sort_order'],
                        'is_active' => $parameterData['is_active'],
                        'ewma_lambda' => $parameterData['ewma_lambda'] ?? $parameter->ewma_lambda,
                        'ewma_level' => $parameterData['ewma_level'] ?? $parameter->ewma_level,
                        'alert_1_min' => $this->nullableValue($parameterData['alert_1_min']),
                        'alert_1_max' => $this->nullableValue($parameterData['alert_1_max']),
                        'alert_2_min' => $this->nullableValue($parameterData['alert_2_min']),
                        'alert_2_max' => $this->nullableValue($parameterData['alert_2_max']),
                        'alert_3_min' => $this->nullableValue($parameterData['alert_3_min']),
                        'alert_3_max' => $this->nullableValue($parameterData['alert_3_max']),
                        'alert_4_min' => $this->nullableValue($parameterData['alert_4_min']),
                        'alert_4_max' => $this->nullableValue($parameterData['alert_4_max']),
                    ]);
                }
            }
        });
    }

    private function nullableValue(mixed $value): mixed
    {
        return $value === '' || $value === null ? null : $value;
    }
}

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
                        'unit' => $parameterData['unit'],
                        'decimals' => $parameterData['decimals'],
                        'sort_order' => $parameterData['sort_order'],
                        'is_active' => $parameterData['is_active'],
                        'alert_1_min' => $this->nullableThreshold($parameterData['alert_1_min']),
                        'alert_1_max' => $this->nullableThreshold($parameterData['alert_1_max']),
                        'alert_2_min' => $this->nullableThreshold($parameterData['alert_2_min']),
                        'alert_2_max' => $this->nullableThreshold($parameterData['alert_2_max']),
                        'alert_3_min' => $this->nullableThreshold($parameterData['alert_3_min']),
                        'alert_3_max' => $this->nullableThreshold($parameterData['alert_3_max']),
                        'alert_4_min' => $this->nullableThreshold($parameterData['alert_4_min']),
                        'alert_4_max' => $this->nullableThreshold($parameterData['alert_4_max']),
                    ]);
                }
            }
        });
    }

    private function nullableThreshold(mixed $value): mixed
    {
        return $value === '' || $value === null ? null : $value;
    }
}

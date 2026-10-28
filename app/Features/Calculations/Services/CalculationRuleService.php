<?php

namespace App\Features\Calculations\Services;

use App\Models\CalculationRule;
use App\Models\CalculationRun;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalculationRuleService
{
    public function __construct(private readonly ParameterCalculationService $calculator) {}

    public function ensureIdle(Team $team): void
    {
        if (CalculationRun::query()->where('team_id', $team->id)->whereIn('status', ['pending', 'running'])->exists()) {
            throw ValidationException::withMessages(['calculation' => 'Aguarde a conclusão do recálculo desta unidade.']);
        }
    }

    /** @param array<string, mixed> $data */
    public function save(Team $team, array $data, ?CalculationRule $rule = null): CalculationRule
    {
        return DB::transaction(function () use ($team, $data, $rule): CalculationRule {
            $this->calculator->lock($team);
            $this->ensureIdle($team);
            if ($rule !== null) {
                $rule = CalculationRule::query()->where('team_id', $team->id)->findOrFail($rule->id);
            }
            $rules = CalculationRule::query()->where('team_id', $team->id)->get()->reject(fn (CalculationRule $item): bool => $item->id === $rule?->id);
            if ($rules->contains('destination_parameter_id', (int) $data['destination_parameter_id'])) {
                throw ValidationException::withMessages(['destination_parameter_id' => 'Este parâmetro já é destino de outro cálculo.']);
            }
            $attributes = array_intersect_key($data, array_flip(['name', 'type', 'primary_parameter_id', 'secondary_parameter_id', 'destination_parameter_id', 'primary_multiplier', 'secondary_multiplier', 'result_multiplier']));
            $candidate = new CalculationRule($attributes + ['team_id' => $team->id]);
            $this->calculator->ordered($rules->push($candidate));
            $rule ??= new CalculationRule;
            $rule->fill($attributes + ['team_id' => $team->id])->save();

            return $rule;
        }, 3);
    }

    public function delete(Team $team, CalculationRule $rule): void
    {
        DB::transaction(function () use ($team, $rule): void {
            $this->calculator->lock($team);
            $this->ensureIdle($team);
            CalculationRule::query()->where('team_id', $team->id)->findOrFail($rule->id)->delete();
        }, 3);
    }
}

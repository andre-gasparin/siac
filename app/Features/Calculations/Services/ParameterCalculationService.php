<?php

namespace App\Features\Calculations\Services;

use App\Models\CalculationRule;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\ParameterValueRevision;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ParameterCalculationService
{
    /** @var array<string, array{label: string, formula: string}> */
    public const TYPES = [
        'cycle' => ['label' => 'Ciclo', 'formula' => 'P / S'],
        'efficiency' => ['label' => 'Eficiência', 'formula' => '(1 − S / P) × 100'],
        'net_loss' => ['label' => 'Perda líquida', 'formula' => 'P / (S − 1)'],
        'ph' => ['label' => 'pH calculado', 'formula' => 'log10((P − S / 3) / 273) + 11'],
        'percentage' => ['label' => 'Relação percentual', 'formula' => '(S / P) × 100'],
        'ratio' => ['label' => 'Relação', 'formula' => 'S / P'],
    ];

    public function lock(Team $team): void
    {
        Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
    }

    public function calculate(CalculationRule $rule, ?float $primary, ?float $secondary): ?float
    {
        if ($primary === null || $secondary === null) {
            return null;
        }
        $p = $primary * ($rule->primary_multiplier ?? 1);
        $s = $secondary * ($rule->secondary_multiplier ?? 1);
        if (! is_finite($p) || ! is_finite($s)) {
            return null;
        }
        $result = match ($rule->type) {
            'cycle' => $s == 0 ? null : $p / $s,
            'efficiency' => $p == 0 ? null : (1 - $s / $p) * 100,
            'net_loss' => $s == 1 ? null : $p / ($s - 1),
            'ph' => ($p - $s / 3) / 273 > 0 ? log10(($p - $s / 3) / 273) + 11 : null,
            'percentage' => $p == 0 ? null : ($s / $p) * 100,
            'ratio' => $p == 0 ? null : $s / $p,
            default => null,
        };
        $result = $result === null ? null : $result * ($rule->result_multiplier ?? 1);

        return $result !== null && is_finite($result) ? $result : null;
    }

    /**
     * @param  iterable<CalculationRule>  $rules
     * @return list<CalculationRule>
     */
    public function ordered(iterable $rules): array
    {
        $pending = [];
        foreach ($rules as $rule) {
            $pending[$rule->destination_parameter_id] = $rule;
        }
        $ordered = [];
        while ($pending !== []) {
            $progress = false;
            foreach ($pending as $destination => $rule) {
                if (isset($pending[$rule->primary_parameter_id]) || isset($pending[$rule->secondary_parameter_id])) {
                    continue;
                }
                $ordered[] = $rule;
                unset($pending[$destination]);
                $progress = true;
            }
            if (! $progress) {
                throw ValidationException::withMessages(['destination_parameter_id' => 'Os cálculos não podem formar dependências circulares.']);
            }
        }

        return $ordered;
    }

    /** @return list<int> */
    public function historicalRuleIds(Team $team, CalculationRule $selected): array
    {
        $rules = $this->ordered(CalculationRule::query()->where('team_id', $team->id)->get());
        $needed = [$selected->id => true];
        $outputs = [$selected->destination_parameter_id => true];
        foreach ($rules as $rule) {
            if (isset($outputs[$rule->primary_parameter_id]) || isset($outputs[$rule->secondary_parameter_id])) {
                $needed[$rule->id] = true;
                $outputs[$rule->destination_parameter_id] = true;
            }
        }
        $inputs = [];
        foreach (array_reverse($rules) as $rule) {
            if (isset($needed[$rule->id]) || isset($inputs[$rule->destination_parameter_id])) {
                $needed[$rule->id] = true;
                $inputs[$rule->primary_parameter_id] = true;
                $inputs[$rule->secondary_parameter_id] = true;
            }
        }

        return array_keys($needed);
    }

    /**
     * Must run inside the caller's transaction, after acquiring the unit lock and writing all inputs.
     *
     * @param  list<int>  $changedParameterIds
     * @param  list<string>  $timestamps
     * @param  list<int>|null  $ruleIds  Explicit rules for historical recalculation.
     * @return array{created: int, updated: int, removed: int}
     */
    public function execute(Team $team, array $changedParameterIds, array $timestamps, ?User $user, ?array $ruleIds = null): array
    {
        $rules = $this->ordered(CalculationRule::query()->where('team_id', $team->id)->get());
        $changed = array_fill_keys($changedParameterIds, true);
        $selected = [];
        foreach ($rules as $rule) {
            if ($ruleIds !== null ? in_array($rule->id, $ruleIds, true) : (isset($changed[$rule->primary_parameter_id]) || isset($changed[$rule->secondary_parameter_id]))) {
                $selected[] = $rule;
                $changed[$rule->destination_parameter_id] = true;
            }
        }
        $counts = ['created' => 0, 'updated' => 0, 'removed' => 0];
        if ($selected === [] || $timestamps === []) {
            return $counts;
        }
        $ids = [];
        foreach ($selected as $rule) {
            array_push($ids, $rule->primary_parameter_id, $rule->secondary_parameter_id, $rule->destination_parameter_id);
        }
        $parameters = Parameter::query()->where('team_id', $team->id)->whereIn('id', array_unique($ids))->get()->keyBy('id');
        foreach (array_unique($timestamps) as $timestamp) {
            $at = Carbon::parse($timestamp);
            $values = ParameterValue::query()->where('team_id', $team->id)->whereIn('parameter_id', $ids)
                ->where('measured_at', $at)->get()->keyBy('parameter_id');
            foreach ($selected as $rule) {
                $destination = $parameters->get($rule->destination_parameter_id);
                if (! $destination) {
                    continue;
                }
                $primary = $parameters->has($rule->primary_parameter_id) ? $values->get($rule->primary_parameter_id)?->value : null;
                $secondary = $parameters->has($rule->secondary_parameter_id) ? $values->get($rule->secondary_parameter_id)?->value : null;
                $result = $this->calculate($rule, $primary, $secondary);
                $existing = $values->get($destination->id);
                if ($result === null && (! $existing || $existing->source_type !== 'calculated')) {
                    continue;
                }
                if ($result !== null && $existing && $existing->value === $result && $existing->source_type === 'calculated' && $existing->getAttribute('calculation_rule_id') === $rule->id) {
                    continue;
                }
                $before = $existing?->value;
                $previousSource = $existing?->source_type;
                if ($result === null) {
                    $existing->delete();
                    $values->forget($destination->id);
                    $counts['removed']++;
                } else {
                    $attributes = ['monitored_system_id' => $destination->monitored_system_id, 'measured_date' => $at->toDateString(),
                        'value' => $result, 'source_type' => 'calculated', 'calculation_rule_id' => $rule->id, 'updated_by' => $user?->id];
                    if ($existing) {
                        $existing->update($attributes);
                        $counts['updated']++;
                    } else {
                        $existing = ParameterValue::query()->create($attributes + ['team_id' => $team->id, 'parameter_id' => $destination->id, 'measured_at' => $at, 'created_by' => $user?->id]);
                        $counts['created']++;
                    }
                    $values->put($destination->id, $existing);
                }
                ParameterValueRevision::query()->create([
                    'team_id' => $team->id, 'monitored_system_id' => $destination->monitored_system_id, 'parameter_id' => $destination->id,
                    'user_id' => $user?->id, 'measured_at' => $at, 'action' => 'cálculo',
                    'changes' => ['parameter' => $destination->name, 'before' => $before, 'after' => $result, 'previous_source_type' => $previousSource,
                        'calculation_rule_id' => $rule->id, 'calculation_name' => $rule->name, 'type' => $rule->type,
                        'primary' => $primary, 'secondary' => $secondary, 'primary_multiplier' => $rule->primary_multiplier,
                        'secondary_multiplier' => $rule->secondary_multiplier, 'result_multiplier' => $rule->result_multiplier],
                ]);
            }
        }

        return $counts;
    }
}

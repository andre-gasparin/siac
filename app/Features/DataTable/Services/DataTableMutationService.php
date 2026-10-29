<?php

namespace App\Features\DataTable\Services;

use App\Features\Calculations\Services\ParameterCalculationService;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\ParameterValueRevision;
use App\Models\ParameterValuesComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DataTableMutationService
{
    public function __construct(private readonly ParameterCalculationService $calculator) {}

    public function updateValue(Team $team, User $user, Parameter $parameter, Carbon $at, ?float $value): void
    {
        DB::transaction(function () use ($team, $user, $parameter, $at, $value): void {
            $this->calculator->lock($team);
            $existing = ParameterValue::query()->where('team_id', $team->id)->where('parameter_id', $parameter->id)->where('measured_at', $at)->lockForUpdate()->first();
            $previous = $existing?->value;

            if ($previous === $value) {
                return;
            }

            if ($value === null) {
                $existing?->delete();
            } elseif ($existing) {
                $existing->update(['value' => $value, 'updated_by' => $user->id, 'source_type' => 'manual', 'calculation_rule_id' => null]);
            } else {
                ParameterValue::query()->create([
                    'team_id' => $team->id, 'monitored_system_id' => $parameter->monitored_system_id, 'parameter_id' => $parameter->id,
                    'measured_at' => $at, 'measured_date' => $at->toDateString(), 'value' => $value, 'source_type' => 'manual', 'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }

            $this->record($team, $user, $parameter->monitored_system_id, $at, 'valor', ['parameter' => $parameter->name, 'before' => $previous, 'after' => $value], $parameter->id);
            $this->calculator->execute($team, [$parameter->id], [$at->toDateTimeString()], $user);
        }, 3);
    }

    /**
     * @param  list<array{parameter_id: int|string, timestamp: string}>  $cells
     */
    public function batchUpdateValues(Team $team, User $user, array $cells, ?float $value): int
    {
        return DB::transaction(function () use ($team, $user, $cells, $value): int {
            $this->calculator->lock($team);

            $parameterIds = array_values(array_unique(array_map(
                fn (array $cell): int => (int) $cell['parameter_id'],
                $cells,
            )));

            $parameters = Parameter::query()
                ->where('team_id', $team->id)
                ->whereIn('id', $parameterIds)
                ->get()
                ->keyBy('id');

            if ($parameters->isEmpty()) {
                return 0;
            }

            $affectedParameterIds = [];
            $affectedTimestamps = [];
            $updatedCount = 0;

            foreach ($cells as $cell) {
                $parameterId = (int) $cell['parameter_id'];
                $parameter = $parameters->get($parameterId);

                if (! $parameter) {
                    continue;
                }

                $at = Carbon::parse($cell['timestamp']);
                $existing = ParameterValue::query()
                    ->where('team_id', $team->id)
                    ->where('parameter_id', $parameter->id)
                    ->where('measured_at', $at)
                    ->lockForUpdate()
                    ->first();

                $previous = $existing?->value;

                if ($previous === $value) {
                    continue;
                }

                if ($value === null) {
                    $existing?->delete();
                } elseif ($existing) {
                    $existing->update([
                        'value' => $value,
                        'updated_by' => $user->id,
                        'source_type' => 'manual',
                        'calculation_rule_id' => null,
                    ]);
                } else {
                    ParameterValue::query()->create([
                        'team_id' => $team->id,
                        'monitored_system_id' => $parameter->monitored_system_id,
                        'parameter_id' => $parameter->id,
                        'measured_at' => $at,
                        'measured_date' => $at->toDateString(),
                        'value' => $value,
                        'source_type' => 'manual',
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }

                $this->record(
                    $team,
                    $user,
                    $parameter->monitored_system_id,
                    $at,
                    'valor',
                    ['parameter' => $parameter->name, 'before' => $previous, 'after' => $value],
                    $parameter->id,
                );

                $affectedParameterIds[$parameter->id] = $parameter->id;
                $affectedTimestamps[$at->toDateTimeString()] = $at->toDateTimeString();
                $updatedCount++;
            }

            if ($affectedParameterIds !== [] && $affectedTimestamps !== []) {
                $this->calculator->execute(
                    $team,
                    array_values($affectedParameterIds),
                    array_values($affectedTimestamps),
                    $user,
                );
            }

            return $updatedCount;
        }, 3);
    }

    public function updateComment(Team $team, User $user, int $systemId, Carbon $at, ?string $comment): void
    {
        DB::transaction(function () use ($team, $user, $systemId, $at, $comment): void {
            $this->calculator->lock($team);
            $existing = ParameterValuesComment::query()->where('team_id', $team->id)->where('monitored_system_id', $systemId)->where('measured_at', $at)->lockForUpdate()->first();
            $comment = trim($comment ?? '');
            $before = $existing ? $existing->comment : '';
            if ($before === $comment) {
                return;
            }

            if ($comment === '') {
                $existing?->delete();
            } else {
                ParameterValuesComment::query()->updateOrCreate(
                    ['team_id' => $team->id, 'monitored_system_id' => $systemId, 'measured_at' => $at],
                    ['measured_date' => $at->toDateString(), 'comment' => $comment, 'created_by' => $user->id],
                );
            }

            $this->record($team, $user, $systemId, $at, 'comentário', ['before' => $before, 'after' => $comment]);
        });
    }

    /** @param list<int> $parameterIds */
    public function deleteRow(Team $team, User $user, Carbon $at, array $parameterIds): int
    {
        return DB::transaction(function () use ($team, $user, $at, $parameterIds): int {
            $this->calculator->lock($team);
            $values = ParameterValue::query()->where('team_id', $team->id)->whereIn('parameter_id', $parameterIds)->where('measured_at', $at)->with('parameter:id,name')->lockForUpdate()->get();
            foreach ($values as $value) {
                $this->record($team, $user, $value->monitored_system_id, $at, 'exclusão', ['parameter' => $value->parameter->name, 'before' => $value->value, 'after' => null], $value->parameter_id);
                $value->delete();
            }

            $systemIds = Parameter::query()->where('team_id', $team->id)->whereIn('id', $parameterIds)->pluck('monitored_system_id')->unique()->all();
            $comments = ParameterValuesComment::query()->where('team_id', $team->id)->whereIn('monitored_system_id', $systemIds)->where('measured_at', $at)->get();
            foreach ($comments as $comment) {
                $this->updateComment($team, $user, $comment->monitored_system_id, $at, '');
            }

            $this->calculator->execute($team, $parameterIds, [$at->toDateTimeString()], $user);

            return $values->count();
        }, 3);
    }

    /** @param list<int> $parameterIds */
    public function moveRow(Team $team, User $user, Carbon $from, Carbon $to, array $parameterIds): int
    {
        return DB::transaction(function () use ($team, $user, $from, $to, $parameterIds): int {
            $this->calculator->lock($team);
            $values = ParameterValue::query()->where('team_id', $team->id)->whereIn('parameter_id', $parameterIds)->where('measured_at', $from)->with('parameter:id,name')->lockForUpdate()->get();
            if ($from->equalTo($to)) {
                return $values->count();
            }

            $systemIds = Parameter::query()->where('team_id', $team->id)->whereIn('id', $parameterIds)->pluck('monitored_system_id')->unique()->all();
            $collision = ParameterValue::query()->where('team_id', $team->id)->whereIn('parameter_id', $parameterIds)->where('measured_at', $to)->exists();
            $commentCollision = ParameterValuesComment::query()->where('team_id', $team->id)->whereIn('monitored_system_id', $systemIds)->where('measured_at', $to)->exists();
            if ($collision || $commentCollision) {
                throw ValidationException::withMessages(['new_timestamp' => 'Já existem leituras ou comentários no horário de destino.']);
            }

            ParameterValueRevision::query()->where('team_id', $team->id)->where('measured_at', $from)
                ->where(function ($query) use ($parameterIds, $systemIds): void {
                    $query->whereIn('parameter_id', $parameterIds)->orWhere(fn ($query) => $query->whereNull('parameter_id')->whereIn('monitored_system_id', $systemIds));
                })->update(['measured_at' => $to]);

            foreach ($values as $value) {
                ParameterValue::query()->where('team_id', $team->id)->whereKey($value->id)->update(['measured_at' => $to, 'measured_date' => $to->toDateString(), 'updated_by' => $user->id]);
                $this->record($team, $user, $value->monitored_system_id, $to, 'data/hora', ['parameter' => $value->parameter->name, 'before' => $from->toDateTimeString(), 'after' => $to->toDateTimeString()], $value->parameter_id);
            }

            ParameterValuesComment::query()->where('team_id', $team->id)->whereIn('monitored_system_id', $systemIds)->where('measured_at', $from)->update(['measured_at' => $to, 'measured_date' => $to->toDateString()]);

            $this->calculator->execute($team, $parameterIds, [$from->toDateTimeString(), $to->toDateTimeString()], $user);

            return $values->count();
        }, 3);
    }

    /** @param array<string, mixed> $changes */
    private function record(Team $team, User $user, int $systemId, Carbon $at, string $action, array $changes, ?int $parameterId = null): void
    {
        ParameterValueRevision::query()->create([
            'team_id' => $team->id, 'monitored_system_id' => $systemId, 'parameter_id' => $parameterId,
            'user_id' => $user->id, 'measured_at' => $at, 'action' => $action, 'changes' => $changes,
        ]);
    }
}

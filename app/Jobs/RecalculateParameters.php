<?php

namespace App\Jobs;

use App\Features\Calculations\Services\ParameterCalculationService;
use App\Models\CalculationRule;
use App\Models\CalculationRun;
use App\Models\ParameterValue;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecalculateParameters implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $runId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 20];
    }

    /**
     * Execute the job.
     */
    public function handle(ParameterCalculationService $calculator): void
    {
        $run = CalculationRun::query()->find($this->runId);
        if (! $run || in_array($run->status, ['completed', 'failed'], true)) {
            return;
        }
        $team = Team::query()->findOrFail($run->team_id);
        DB::transaction(function () use ($team, $calculator): void {
            $calculator->lock($team);
            $run = CalculationRun::query()->lockForUpdate()->findOrFail($this->runId);
            if (in_array($run->status, ['completed', 'failed'], true)) {
                return;
            }
            $rules = CalculationRule::query()->where('team_id', $team->id)->whereIn('id', $run->rule_ids)->get();
            $ids = [];
            foreach ($rules as $rule) {
                array_push($ids, $rule->primary_parameter_id, $rule->secondary_parameter_id, $rule->destination_parameter_id);
            }
            $query = ParameterValue::query()->where('team_id', $team->id)->whereIn('parameter_id', array_unique($ids))
                ->where('measured_at', '>=', $run->start_date->startOfDay())
                ->where('measured_at', '<', $run->end_date->copy()->addDay()->startOfDay());
            if ($run->status === 'pending') {
                $run->total_count = (clone $query)->distinct()->count('measured_at');
            }
            if ($run->cursor_at !== null) {
                $query->where('measured_at', '>', $run->cursor_at);
            }
            $timestamps = $query->select('measured_at')->distinct()->orderBy('measured_at')->limit(100)->get()
                ->map(fn (ParameterValue $value): string => $value->measured_at->toDateTimeString())->all();
            $counts = $calculator->execute($team, [], array_values($timestamps), User::query()->find($run->user_id), $run->rule_ids);
            $run->processed_count += count($timestamps);
            $run->created_count += $counts['created'];
            $run->updated_count += $counts['updated'];
            $run->removed_count += $counts['removed'];
            $run->status = count($timestamps) < 100 ? 'completed' : 'running';
            $run->cursor_at = $timestamps === [] ? $run->cursor_at : $timestamps[array_key_last($timestamps)];
            $run->setAttribute('finished_at', $run->status === 'completed' ? now() : null);
            $run->save();
            if ($run->status === 'running') {
                self::dispatch($run->id)->afterCommit();
            }
        }, 3);
    }

    public function failed(?Throwable $exception): void
    {
        CalculationRun::query()->whereKey($this->runId)->whereIn('status', ['pending', 'running'])->update([
            'status' => 'failed', 'finished_at' => now(), 'error' => 'Não foi possível concluir o recálculo. Tente novamente ou consulte o suporte.',
        ]);
    }
}

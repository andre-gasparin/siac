<?php

namespace App\Features\Reports\Agents\Support;

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterDailyMetric;
use App\Models\ParameterValue;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReportDataQuery
{
    /**
     * @return list<array{name: string, description: string, arguments: array<string, string>}>
     */
    public function catalog(): array
    {
        return [
            [
                'name' => 'search_catalog',
                'description' => 'Busca sistemas e parâmetros ativos da empresa atual.',
                'arguments' => ['query' => 'texto opcional', 'limit' => '1 a 20'],
            ],
            [
                'name' => 'get_operational_metrics',
                'description' => 'Consulta médias, mínimos, máximos e quantidade de medições por período.',
                'arguments' => [
                    'system_id' => 'ID do sistema',
                    'parameter_ids' => 'IDs opcionais, máximo 20',
                    'start_date' => 'YYYY-MM-DD',
                    'end_date' => 'YYYY-MM-DD, período máximo 90 dias',
                ],
            ],
            [
                'name' => 'search_previous_comments',
                'description' => 'Busca comentários anteriores de um sistema.',
                'arguments' => [
                    'system_id' => 'ID do sistema',
                    'before_date' => 'YYYY-MM-DD',
                    'query' => 'texto opcional',
                    'limit' => '1 a 10',
                ],
            ],
            [
                'name' => 'search_previous_reports',
                'description' => 'Busca relatórios anteriores da empresa por período ou texto.',
                'arguments' => [
                    'start_date' => 'YYYY-MM-DD opcional',
                    'end_date' => 'YYYY-MM-DD opcional',
                    'query' => 'texto opcional',
                    'limit' => '1 a 10',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{result: mixed, activity: string}
     */
    public function execute(Team $team, string $name, array $arguments): array
    {
        return match ($name) {
            'search_catalog' => $this->searchCatalog($team, $arguments),
            'get_operational_metrics' => $this->operationalMetrics($team, $arguments),
            'search_previous_comments' => $this->previousComments($team, $arguments),
            'search_previous_reports' => $this->previousReports($team, $arguments),
            default => throw ValidationException::withMessages([
                'tool_calls' => "A ferramenta {$name} não é permitida.",
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{result: mixed, activity: string}
     */
    public function searchCatalog(Team $team, array $arguments): array
    {
        $data = Validator::make(Arr::only($arguments, ['query', 'limit']), [
            'query' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'between:1,20'],
        ])->validate();
        $query = trim((string) ($data['query'] ?? ''));
        $limit = (int) ($data['limit'] ?? 20);

        $parameters = Parameter::query()
            ->whereBelongsTo($team)
            ->where('is_active', true)
            ->whereHas('monitoredSystem', fn (Builder $builder) => $builder->where('is_active', true))
            ->with('monitoredSystem:id,name')
            ->when($query !== '', function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $search) use ($query): void {
                    $search->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%")
                        ->orWhere('tag', 'like', "%{$query}%")
                        ->orWhereHas('monitoredSystem', fn (Builder $system) => $system->where('name', 'like', "%{$query}%"));
                });
            })
            ->orderBy('monitored_system_id')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get(['id', 'monitored_system_id', 'name', 'unit']);

        return [
            'result' => $parameters->map(fn (Parameter $parameter): array => [
                'system_id' => $parameter->monitored_system_id,
                'system' => $parameter->monitoredSystem->name,
                'parameter_id' => $parameter->id,
                'parameter' => $parameter->name,
                'unit' => $parameter->unit,
            ])->all(),
            'activity' => 'Consultou sistemas e parâmetros da empresa',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{result: mixed, activity: string}
     */
    public function operationalMetrics(Team $team, array $arguments): array
    {
        $data = Validator::make(Arr::only($arguments, ['system_id', 'parameter_ids', 'start_date', 'end_date']), [
            'system_id' => ['required', 'integer'],
            'parameter_ids' => ['nullable', 'array', 'max:20'],
            'parameter_ids.*' => ['integer', 'distinct'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ])->after(function ($validator) use ($arguments): void {
            if (
                isset($arguments['start_date'], $arguments['end_date']) &&
                Carbon::parse($arguments['start_date'])->diffInDays(Carbon::parse($arguments['end_date'])) > 89
            ) {
                $validator->errors()->add('end_date', 'O período máximo é de 90 dias.');
            }
        })->validate();

        $system = MonitoredSystem::query()
            ->whereBelongsTo($team)
            ->whereKey((int) $data['system_id'])
            ->where('is_active', true)
            ->firstOrFail();
        $parameterIds = collect($data['parameter_ids'] ?? [])->map(fn ($id): int => (int) $id);
        $parameters = Parameter::query()
            ->whereBelongsTo($team)
            ->whereBelongsTo($system, 'monitoredSystem')
            ->where('is_active', true)
            ->when($parameterIds->isNotEmpty(), fn (Builder $query) => $query->whereIn('id', $parameterIds))
            ->limit(20)
            ->get(['id', 'name', 'unit'])
            ->keyBy('id');

        if ($parameterIds->isNotEmpty() && $parameters->count() !== $parameterIds->unique()->count()) {
            throw ValidationException::withMessages(['parameter_ids' => 'Há parâmetros fora do sistema ou da empresa.']);
        }

        $metrics = ParameterDailyMetric::query()
            ->whereBelongsTo($team)
            ->whereBelongsTo($system, 'monitoredSystem')
            ->whereIn('parameter_id', $parameters->keys())
            ->whereBetween('measured_date', [$data['start_date'], $data['end_date']])
            ->selectRaw('parameter_id, SUM(values_count) as values_count, AVG(average_value) as average_value, MIN(minimum_value) as minimum_value, MAX(maximum_value) as maximum_value, SUM(out_of_limit_count) as out_of_limit_count')
            ->groupBy('parameter_id')
            ->get()
            ->keyBy('parameter_id');

        $missingIds = $parameters->keys()->diff($metrics->keys());
        $fallback = ParameterValue::query()
            ->whereBelongsTo($team)
            ->whereBelongsTo($system, 'monitoredSystem')
            ->whereIn('parameter_id', $missingIds)
            ->whereBetween('measured_date', [$data['start_date'], $data['end_date']])
            ->selectRaw('parameter_id, COUNT(value) as values_count, AVG(value) as average_value, MIN(value) as minimum_value, MAX(value) as maximum_value')
            ->groupBy('parameter_id')
            ->get()
            ->keyBy('parameter_id');

        return [
            'result' => $parameters->map(function (Parameter $parameter) use ($metrics, $fallback): array {
                $metric = $metrics->get($parameter->id) ?? $fallback->get($parameter->id);

                return [
                    'parameter_id' => $parameter->id,
                    'parameter' => $parameter->name,
                    'unit' => $parameter->unit,
                    'count' => (int) ($metric?->values_count ?? 0),
                    'average' => $metric?->average_value !== null ? round((float) $metric->average_value, 3) : null,
                    'minimum' => $metric?->minimum_value !== null ? (float) $metric->minimum_value : null,
                    'maximum' => $metric?->maximum_value !== null ? (float) $metric->maximum_value : null,
                    'out_of_limit' => (int) ($metric?->out_of_limit_count ?? 0),
                ];
            })->values()->all(),
            'activity' => sprintf(
                'Consultou dados de %s a %s em %s',
                Carbon::parse($data['start_date'])->format('d/m/Y'),
                Carbon::parse($data['end_date'])->format('d/m/Y'),
                $system->name,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{result: mixed, activity: string}
     */
    public function previousComments(Team $team, array $arguments): array
    {
        $data = Validator::make(Arr::only($arguments, ['system_id', 'before_date', 'query', 'limit']), [
            'system_id' => ['required', 'integer'],
            'before_date' => ['required', 'date_format:Y-m-d'],
            'query' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'between:1,10'],
        ])->validate();
        $system = MonitoredSystem::query()->whereBelongsTo($team)->whereKey((int) $data['system_id'])->firstOrFail();
        $query = trim((string) ($data['query'] ?? ''));

        $items = ReportItem::query()
            ->whereBelongsTo($team)
            ->whereBelongsTo($system, 'monitoredSystem')
            ->whereNotNull('comment')
            ->whereDate('date_reference', '<', $data['before_date'])
            ->when($query !== '', fn (Builder $builder) => $builder->where('comment', 'like', "%{$query}%"))
            ->with('report:id,title,date_reference,status')
            ->latest('date_reference')
            ->latest('id')
            ->limit((int) ($data['limit'] ?? 10))
            ->get();

        return [
            'result' => $items->map(fn (ReportItem $item): array => [
                'date' => $item->report->date_reference?->format('d/m/Y'),
                'report' => $item->report->title,
                'status' => $item->report->status,
                'comment' => mb_substr(trim(strip_tags((string) $item->comment)), 0, 1000),
            ])->all(),
            'activity' => "Consultou comentários anteriores de {$system->name}",
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{result: mixed, activity: string}
     */
    public function previousReports(Team $team, array $arguments): array
    {
        $data = Validator::make(Arr::only($arguments, ['start_date', 'end_date', 'query', 'limit']), [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'query' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'between:1,10'],
        ])->validate();
        $query = trim((string) ($data['query'] ?? ''));

        $reports = Report::query()
            ->whereBelongsTo($team)
            ->when($data['start_date'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('date_reference', '>=', $date))
            ->when($data['end_date'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('date_reference', '<=', $date))
            ->when($query !== '', function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $search) use ($query): void {
                    $search->where('title', 'like', "%{$query}%")
                        ->orWhereHas('items', fn (Builder $items) => $items->where('comment', 'like', "%{$query}%"));
                });
            })
            ->with(['items' => fn ($items) => $items->with('monitoredSystem:id,name')->orderBy('sort_order')->limit(20)])
            ->latest('date_reference')
            ->limit((int) ($data['limit'] ?? 10))
            ->get(['id', 'title', 'date_reference', 'status']);

        return [
            'result' => $reports->map(fn (Report $report): array => [
                'date' => $report->date_reference?->format('d/m/Y'),
                'title' => $report->title,
                'status' => $report->status,
                'items' => $report->items->map(fn (ReportItem $item): array => [
                    'system' => $item->monitoredSystem?->name,
                    'comment' => mb_substr(trim(strip_tags((string) $item->comment)), 0, 600),
                ])->all(),
            ])->all(),
            'activity' => 'Consultou relatórios anteriores da empresa',
        ];
    }
}

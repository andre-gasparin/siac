<?php

namespace App\Features\StatisticalAnalysis\Services;

use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StatisticalAnalysisBuilder
{
    public function __construct(
        protected EwmaCalculator $ewmaCalculator,
        protected CusumCalculator $cusumCalculator,
    ) {}

    /**
     * @param  array{
     *     system_id: int,
     *     parameter_ids: list<int>,
     *     start_date: string,
     *     end_date: string,
     *     lookback_value?: int|null,
     *     lookback_unit?: string|null,
     *     frequency?: string|null,
     *     num_alert_levels?: int|null,
     *     ewma_lambda?: float|null,
     *     ewma_levels?: list<float>|null,
     *     cusum_k?: float|null,
     *     cusum_levels?: list<float>|null,
     *     manual_overrides?: array<int, array{mean?: float|null, std_dev?: float|null}>|null
     * }  $filters
     * @return array<string, mixed>
     */
    public function build(Team $team, array $filters): array
    {
        $systemId = (int) $filters['system_id'];
        $parameterIds = array_values(array_filter($filters['parameter_ids']));

        if ($parameterIds === []) {
            return [
                'parameters' => [],
                'results' => [],
            ];
        }

        $parameters = Parameter::query()
            ->where('team_id', $team->id)
            ->where('monitored_system_id', $systemId)
            ->whereIn('id', $parameterIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($parameters->isEmpty()) {
            return [
                'parameters' => [],
                'results' => [],
            ];
        }

        $startDate = Carbon::parse($filters['start_date'])->startOfDay();
        $endDate = Carbon::parse($filters['end_date'])->endOfDay();

        $lookbackValue = max(1, (int) ($filters['lookback_value'] ?? 30));
        $lookbackUnit = $filters['lookback_unit'] ?? 'days';
        $lookbackUnit = in_array($lookbackUnit, ['days', 'samples'], true)
            ? $lookbackUnit
            : 'days';
        $frequency = $filters['frequency'] ?? 'raw';
        $frequency = in_array($frequency, ['raw', 'daily_avg'], true)
            ? $frequency
            : 'raw';

        $numAlertLevels = max(1, min(3, (int) ($filters['num_alert_levels'] ?? 2)));

        $ewmaLambda = max(0.01, min(1.0, (float) ($filters['ewma_lambda'] ?? 0.20)));
        $ewmaLevels = $this->resolveEwmaLevels($filters['ewma_levels'] ?? null, $numAlertLevels);

        $cusumK = max(0.01, min(5.0, (float) ($filters['cusum_k'] ?? 0.50)));
        $cusumLevels = $this->resolveCusumLevels($filters['cusum_levels'] ?? null, $numAlertLevels);

        $manualOverrides = $filters['manual_overrides'] ?? [];

        // Determine baseline start boundary
        $baselineStart = $lookbackUnit === 'days'
            ? $startDate->copy()->subDays($lookbackValue)->startOfDay()
            : null;

        $results = [];

        foreach ($parameters as $parameter) {
            $analysisResult = $this->analyzeParameter(
                team: $team,
                parameter: $parameter,
                startDate: $startDate,
                endDate: $endDate,
                baselineStart: $baselineStart,
                lookbackValue: $lookbackValue,
                lookbackUnit: $lookbackUnit,
                frequency: $frequency,
                ewmaLambda: $ewmaLambda,
                ewmaLevels: $ewmaLevels,
                cusumK: $cusumK,
                cusumLevels: $cusumLevels,
                manualOverride: $manualOverrides[$parameter->id] ?? null,
            );

            if ($analysisResult !== null) {
                $results[$parameter->id] = $analysisResult;
            }
        }

        return [
            'config' => [
                'system_id' => $systemId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'lookback_value' => $lookbackValue,
                'lookback_unit' => $lookbackUnit,
                'frequency' => $frequency,
                'num_alert_levels' => $numAlertLevels,
                'ewma_lambda' => $ewmaLambda,
                'ewma_levels' => $ewmaLevels,
                'cusum_k' => $cusumK,
                'cusum_levels' => $cusumLevels,
            ],
            'parameters' => $parameters->map(fn (Parameter $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'tag' => $p->tag ?? $p->code,
                'unit' => $p->unit,
                'decimals' => $p->decimals,
            ])->values()->all(),
            'results' => $results,
        ];
    }

    /**
     * @param  list<float>  $ewmaLevels
     * @param  list<float>  $cusumLevels
     * @param  array{mean?: float|null, std_dev?: float|null}|null  $manualOverride
     * @return array<string, mixed>|null
     */
    protected function analyzeParameter(
        Team $team,
        Parameter $parameter,
        Carbon $startDate,
        Carbon $endDate,
        ?Carbon $baselineStart,
        int $lookbackValue,
        string $lookbackUnit,
        string $frequency,
        float $ewmaLambda,
        array $ewmaLevels,
        float $cusumK,
        array $cusumLevels,
        ?array $manualOverride,
    ): ?array {
        // Fetch baseline values
        if ($lookbackUnit === 'days') {
            $baselineValues = ParameterValue::query()
                ->where('team_id', $team->id)
                ->where('parameter_id', $parameter->id)
                ->where('measured_at', '>=', $baselineStart)
                ->where('measured_at', '<', $startDate)
                ->whereNotNull('value')
                ->orderBy('measured_at')
                ->get(['measured_at', 'value']);
        } else {
            // 'samples': get the last N samples before $startDate
            $baselineValues = ParameterValue::query()
                ->where('team_id', $team->id)
                ->where('parameter_id', $parameter->id)
                ->where('measured_at', '<', $startDate)
                ->whereNotNull('value')
                ->orderByDesc('measured_at')
                ->take($lookbackValue)
                ->get(['measured_at', 'value'])
                ->reverse()
                ->values();
        }

        // Fetch analysis period values
        $analysisValues = ParameterValue::query()
            ->where('team_id', $team->id)
            ->where('parameter_id', $parameter->id)
            ->whereBetween('measured_at', [$startDate, $endDate])
            ->whereNotNull('value')
            ->orderBy('measured_at')
            ->get(['measured_at', 'value']);

        // Aggregate points if frequency is 'daily_avg'
        $baselinePoints = $this->preparePoints($baselineValues, $frequency);
        $analysisPoints = $this->preparePoints($analysisValues, $frequency);

        if ($analysisPoints === [] && $baselinePoints === []) {
            return null;
        }

        // Compute baseline mean (mu0) and standard deviation (sigma0)
        $baselineNumbers = array_column($baselinePoints, 'value');
        if ($baselineNumbers === []) {
            // If baseline has no points, fall back to analysis points for baseline estimation
            $baselineNumbers = array_column($analysisPoints, 'value');
        }

        $calculatedMu0 = $this->computeMean($baselineNumbers);
        $calculatedSigma0 = $this->computeStdDev($baselineNumbers, $calculatedMu0);

        // Apply manual overrides if given
        $mu0 = $manualOverride['mean'] ?? $calculatedMu0;

        $sigma0 = $manualOverride['std_dev'] ?? $calculatedSigma0;

        // Ensure sigma0 is never 0 to avoid division by zero or flat bounds
        if ($sigma0 <= 0) {
            $sigma0 = max(0.01, abs($mu0) * 0.05);
        }

        // Combine warmup (baseline) points + analysis points
        $warmupCount = count($baselinePoints);
        $allPoints = array_merge($baselinePoints, $analysisPoints);

        // Calculate EWMA
        $ewmaData = $this->ewmaCalculator->calculate(
            points: $allPoints,
            mu0: $mu0,
            sigma0: $sigma0,
            lambda: $ewmaLambda,
            multipliers: $ewmaLevels,
            warmupOffset: $warmupCount,
        );

        // Calculate CUSUM
        $cusumData = $this->cusumCalculator->calculate(
            points: $allPoints,
            mu0: $mu0,
            sigma0: $sigma0,
            kFactor: $cusumK,
            decisionIntervals: $cusumLevels,
            warmupOffset: $warmupCount,
        );

        // Slice out the analysis period series (omitting warmup from main audit table, but keeping summary)
        $analysisRows = [];
        $totalAll = count($allPoints);

        for ($i = $warmupCount; $i < $totalAll; $i++) {
            $ewmaPoint = $ewmaData['series'][$i];
            $cusumPoint = $cusumData['series'][$i];

            $analysisRows[] = [
                'index' => $i - $warmupCount + 1,
                'overall_index' => $i + 1,
                'timestamp' => $ewmaPoint['timestamp'],
                'date' => $ewmaPoint['date'],
                'time' => $ewmaPoint['time'],
                'raw_value' => $ewmaPoint['raw_value'],
                'ewma' => [
                    'z_value' => $ewmaPoint['z_value'],
                    'sigma_z' => $ewmaPoint['sigma_z'],
                    'alert_level' => $ewmaPoint['alert_level'],
                    'violated_side' => $ewmaPoint['violated_side'],
                    'levels' => $ewmaPoint['levels'],
                    'closest_limit' => $ewmaPoint['closest_limit'],
                    'memorial' => $ewmaPoint['memorial'],
                ],
                'cusum' => [
                    'c_pos' => $cusumPoint['c_pos'],
                    'c_neg' => $cusumPoint['c_neg'],
                    'k_value' => $cusumPoint['k_value'],
                    'alert_level' => $cusumPoint['alert_level'],
                    'violated_side' => $cusumPoint['violated_side'],
                    'levels' => $cusumPoint['levels'],
                    'closest_threshold' => $cusumPoint['closest_threshold'],
                    'memorial' => $cusumPoint['memorial'],
                ],
            ];
        }

        // Build chart-ready series for analysis period
        $chartData = [
            'timestamps' => array_column($analysisRows, 'timestamp'),
            'dates' => array_column($analysisRows, 'date'),
            'raw_values' => array_column($analysisRows, 'raw_value'),
            'ewma' => [
                'z_values' => array_map(fn ($r) => $r['ewma']['z_value'], $analysisRows),
                'center_line' => $mu0,
                'levels' => $ewmaLevels,
                'ucl_series' => $this->extractEwmaLevelSeries($analysisRows, 'ucl'),
                'lcl_series' => $this->extractEwmaLevelSeries($analysisRows, 'lcl'),
            ],
            'cusum' => [
                'c_pos_values' => array_map(fn ($r) => $r['cusum']['c_pos'], $analysisRows),
                'c_neg_values' => array_map(fn ($r) => $r['cusum']['c_neg'], $analysisRows),
                'levels' => $cusumLevels,
                'h_thresholds' => array_map(fn ($lvl) => $lvl * $sigma0, $cusumLevels),
            ],
        ];

        return [
            'parameter_id' => $parameter->id,
            'parameter_name' => $parameter->name,
            'unit' => $parameter->unit,
            'decimals' => $parameter->decimals,
            'baseline' => [
                'count' => count($baselineNumbers),
                'calculated_mu_0' => round($calculatedMu0, 4),
                'calculated_sigma_0' => round($calculatedSigma0, 4),
                'effective_mu_0' => round($mu0, 4),
                'effective_sigma_0' => round($sigma0, 4),
                'is_overridden_mean' => isset($manualOverride['mean']),
                'is_overridden_std_dev' => isset($manualOverride['std_dev']),
            ],
            'summary' => [
                'total_analyzed' => count($analysisRows),
                'ewma' => [
                    'in_control_count' => $ewmaData['in_control_count'],
                    'in_control_percentage' => $ewmaData['in_control_percentage'],
                    'alert_counts' => $ewmaData['alert_counts'],
                ],
                'cusum' => [
                    'in_control_count' => $cusumData['in_control_count'],
                    'in_control_percentage' => $cusumData['in_control_percentage'],
                    'alert_counts' => $cusumData['alert_counts'],
                ],
            ],
            'chart_data' => $chartData,
            'rows' => $analysisRows,
        ];
    }

    /**
     * @param  Collection<int, ParameterValue>  $values
     * @return list<array{timestamp: string, date: string, time: string, value: float}>
     */
    protected function preparePoints(Collection $values, string $frequency): array
    {
        if ($values->isEmpty()) {
            return [];
        }

        if ($frequency === 'daily_avg') {
            $grouped = [];
            foreach ($values as $val) {
                $dateKey = $val->measured_at->format('Y-m-d');
                $grouped[$dateKey][] = (float) $val->value;
            }

            $points = [];
            foreach ($grouped as $dateKey => $nums) {
                $avg = array_sum($nums) / count($nums);
                $points[] = [
                    'timestamp' => $dateKey.' 12:00',
                    'date' => Carbon::parse($dateKey)->format('d/m/Y'),
                    'time' => 'Média',
                    'value' => (float) $avg,
                ];
            }

            return $points;
        }

        // 'raw' points
        $points = [];
        foreach ($values as $val) {
            $points[] = [
                'timestamp' => $val->measured_at->format('Y-m-d H:i'),
                'date' => $val->measured_at->format('d/m/Y'),
                'time' => $val->measured_at->format('H:i'),
                'value' => (float) $val->value,
            ];
        }

        return $points;
    }

    /**
     * @param  list<float>  $numbers
     */
    protected function computeMean(array $numbers): float
    {
        $count = count($numbers);
        if ($count === 0) {
            return 0.0;
        }

        return array_sum($numbers) / $count;
    }

    /**
     * @param  list<float>  $numbers
     */
    protected function computeStdDev(array $numbers, float $mean): float
    {
        $count = count($numbers);
        if ($count <= 1) {
            return 1.0;
        }

        $variance = 0.0;
        foreach ($numbers as $num) {
            $variance += pow($num - $mean, 2);
        }

        return sqrt($variance / ($count - 1));
    }

    /**
     * @param  list<float>|null  $levels
     * @return list<float>
     */
    protected function resolveEwmaLevels(?array $levels, int $numLevels): array
    {
        $defaults = [1.5, 2.0, 3.0];

        if (! is_array($levels) || $levels === []) {
            return array_slice($defaults, 0, $numLevels);
        }

        $result = [];
        for ($i = 0; $i < $numLevels; $i++) {
            $val = $levels[$i] ?? $defaults[$i];
            $result[] = max(0.1, $val);
        }

        sort($result);

        return $result;
    }

    /**
     * @param  list<float>|null  $levels
     * @return list<float>
     */
    protected function resolveCusumLevels(?array $levels, int $numLevels): array
    {
        $defaults = [3.5, 4.5, 5.5];

        if (! is_array($levels) || $levels === []) {
            return array_slice($defaults, 0, $numLevels);
        }

        $result = [];
        for ($i = 0; $i < $numLevels; $i++) {
            $val = $levels[$i] ?? $defaults[$i];
            $result[] = max(0.5, $val);
        }

        sort($result);

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, list<float>>
     */
    protected function extractEwmaLevelSeries(array $rows, string $type): array
    {
        $levelSeries = [];

        foreach ($rows as $row) {
            foreach ($row['ewma']['levels'] as $lvl) {
                $levelNum = $lvl['level'];
                $levelSeries[$levelNum][] = $lvl[$type];
            }
        }

        return $levelSeries;
    }
}

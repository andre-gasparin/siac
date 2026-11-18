<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Http\Requests\ChartTemplateRequest;
use App\Features\Reports\Services\ConsolidatedReportService;
use App\Http\Controllers\Controller;
use App\Models\ChartSeries;
use App\Models\ChartTemplate;
use App\Models\Parameter;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChartTemplateController extends Controller
{
    /**
     * List all chart templates for the current team.
     */
    public function index(Request $request, Team $current_team): JsonResponse
    {
        $templates = ChartTemplate::query()
            ->where('team_id', $current_team->id)
            ->with($this->templateRelations())
            ->orderByDesc('is_favorite')
            ->orderBy('name')
            ->get();

        $data = $templates->map(fn (ChartTemplate $template) => $this->formatTemplate($template));

        return response()->json($data);
    }

    /**
     * Create a new chart template.
     */
    public function store(ChartTemplateRequest $request, Team $current_team): JsonResponse
    {
        $validated = $request->validated();

        $template = DB::transaction(function () use ($validated, $current_team, $request) {
            $template = ChartTemplate::create([
                'team_id' => $current_team->id,
                'user_id' => $request->user()?->id,
                'name' => $validated['name'],
                'is_favorite' => $validated['is_favorite'] ?? true,
                'options' => $validated['options'] ?? null,
            ]);

            $this->syncSeries($template, $current_team, $validated['series']);

            return $template;
        });

        $template->load($this->templateRelations());

        return response()->json($this->formatTemplate($template), 201);
    }

    /**
     * Update an existing chart template.
     */
    public function update(ChartTemplateRequest $request, Team $current_team, ChartTemplate $chart_template): JsonResponse
    {
        if ($chart_template->team_id !== $current_team->id) {
            return response()->json(['message' => 'Unauthorized team access'], 403);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $current_team, $chart_template) {
            $chart_template->update([
                'name' => $validated['name'],
                'is_favorite' => $validated['is_favorite'] ?? $chart_template->is_favorite,
                'options' => $validated['options'] ?? $chart_template->options,
            ]);

            $chart_template->series()->delete();
            $this->syncSeries($chart_template, $current_team, $validated['series']);
        });

        $chart_template->load($this->templateRelations());

        return response()->json($this->formatTemplate($chart_template));
    }

    /**
     * Delete a chart template.
     */
    public function destroy(Request $request, Team $current_team, ChartTemplate $chart_template): JsonResponse
    {
        if ($chart_template->team_id !== $current_team->id) {
            return response()->json(['message' => 'Unauthorized team access'], 403);
        }

        $chart_template->delete();

        return response()->json(null, 204);
    }

    /**
     * Eager load constraints for chart template series.
     *
     * @return array<int|string, mixed>
     */
    private function templateRelations(): array
    {
        return [
            'series' => fn ($q) => $q->orderBy('sort_order'),
            'series.parameter:id,monitored_system_id,name,unit,code,tag,alert_1_min,alert_1_max',
            'series.monitoredSystem:id,name',
        ];
    }

    /**
     * Persist ordered series items for a chart template.
     *
     * @param  list<array<string, mixed>>  $series
     */
    private function syncSeries(ChartTemplate $template, Team $team, array $series): void
    {
        foreach ($series as $index => $item) {
            $parameter = Parameter::query()
                ->where('team_id', $team->id)
                ->whereKey((int) $item['parameter_id'])
                ->firstOrFail();

            $template->series()->create([
                'team_id' => $team->id,
                'monitored_system_id' => $parameter->monitored_system_id,
                'parameter_id' => $parameter->id,
                'axis' => ($item['axis_position'] ?? 'left') === 'right' ? 2 : 1,
                'sort_order' => $index,
                'label' => $item['label'] ?? $parameter->name,
                'color' => $item['color'],
                'options' => [
                    'chart_type' => $item['chart_type'],
                    'stroke_width' => $item['stroke_width'] ?? 2,
                    'show_points' => $item['show_points'] ?? true,
                    'show_values' => $item['show_values'] ?? false,
                    'show_limits' => isset($item['show_limits'])
                        ? (bool) $item['show_limits']
                        : ($index === 0),
                    'min_val' => $item['min_val'] ?? null,
                    'max_val' => $item['max_val'] ?? null,
                ],
            ]);
        }
    }

    /**
     * Format a ChartTemplate for JSON response.
     *
     * @return array<string, mixed>
     */
    private function formatTemplate(ChartTemplate $template): array
    {
        $seriesList = $template->series->values();
        $resolvedColors = ConsolidatedReportService::resolveSeriesColors(
            array_values($seriesList->map(fn (ChartSeries $seriesItem): ?string => $seriesItem->color)->all())
        );

        return [
            'id' => $template->id,
            'name' => $template->name,
            'is_favorite' => (bool) $template->is_favorite,
            'options' => $template->options,
            'series' => $seriesList->map(function (ChartSeries $seriesItem, int $index) use ($resolvedColors): array {
                $options = $seriesItem->options ?? [];
                $hasShowLimits = array_key_exists('show_limits', $options) && $options['show_limits'] !== null;
                $hasCustomLimits = ($options['min_val'] ?? null) !== null || ($options['max_val'] ?? null) !== null;
                $showLimits = $hasShowLimits
                    ? (bool) $options['show_limits']
                    : ($hasCustomLimits || $index === 0);
                $parameterMin = $seriesItem->parameter->alert_1_min;
                $parameterMax = $seriesItem->parameter->alert_1_max;
                $minVal = $hasShowLimits
                    ? ($options['min_val'] ?? null)
                    : ($options['min_val'] ?? $parameterMin);
                $maxVal = $hasShowLimits
                    ? ($options['max_val'] ?? null)
                    : ($options['max_val'] ?? $parameterMax);

                return [
                    'id' => $seriesItem->id,
                    'parameter_id' => $seriesItem->parameter_id,
                    'system_name' => $seriesItem->monitoredSystem->name ?? 'Sistema',
                    'label' => $seriesItem->label ?? $seriesItem->parameter->name ?? '',
                    'chart_type' => $options['chart_type'] ?? 'line',
                    'color' => $resolvedColors[$index],
                    'stroke_width' => $options['stroke_width'] ?? 2,
                    'axis_position' => $seriesItem->axis === 2 ? 'right' : 'left',
                    'min_val' => $minVal !== null ? (float) $minVal : null,
                    'max_val' => $maxVal !== null ? (float) $maxVal : null,
                    'parameter_min' => $parameterMin !== null ? (float) $parameterMin : null,
                    'parameter_max' => $parameterMax !== null ? (float) $parameterMax : null,
                    'show_limits' => $showLimits,
                    'show_points' => $options['show_points'] ?? true,
                    'show_values' => $options['show_values'] ?? false,
                ];
            })->all(),
        ];
    }
}

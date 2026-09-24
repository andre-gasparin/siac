<?php

namespace App\Features\Reports\Agents\Support;

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ReportSuggestionFormatter
{
    private const COLORS = ['#2563eb', '#16a34a', '#ea580c', '#7c3aed', '#dc2626'];

    /**
     * @param  array<string, mixed>  $suggestion
     * @return array{document: array<string, mixed>, preview: string}|null
     */
    public function format(
        Team $team,
        MonitoredSystem $system,
        string $reportDate,
        array $suggestion,
    ): ?array {
        $text = $this->cleanText((string) ($suggestion['text'] ?? ''));

        if ($text === '') {
            return null;
        }

        $requestedIds = collect($suggestion['parameter_ids'] ?? [])
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->take(5);

        $parameters = Parameter::query()
            ->whereBelongsTo($team)
            ->whereBelongsTo($system, 'monitoredSystem')
            ->where('is_active', true)
            ->when($requestedIds->isNotEmpty(), fn (Builder $query) => $query->whereIn('id', $requestedIds))
            ->orderBy('sort_order')
            ->limit(5)
            ->get(['id', 'name']);

        if ($parameters->isEmpty()) {
            $parameters = Parameter::query()
                ->whereBelongsTo($team)
                ->whereBelongsTo($system, 'monitoredSystem')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->limit(3)
                ->get(['id', 'name']);
        }

        if ($parameters->isEmpty()) {
            return null;
        }

        $kind = in_array($suggestion['icon'] ?? null, ['error', 'warning', 'success', 'info'], true)
            ? $suggestion['icon']
            : 'info';
        $label = $parameters->pluck('name')->join(', ', ' e ');
        $endDate = Carbon::parse($reportDate);
        $startDate = $endDate->copy()->subDays(6);
        $series = $parameters->values()->map(fn (Parameter $parameter, int $index): array => [
            'parameter_id' => $parameter->id,
            'label' => $parameter->name,
            'chart_type' => 'line',
            'color' => self::COLORS[$index],
            'stroke_width' => 2,
            'axis_position' => $index === 1 ? 'right' : 'left',
            'min_val' => null,
            'max_val' => null,
            'show_points' => true,
            'show_values' => false,
        ])->all();

        return [
            'document' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'reportIcon', 'attrs' => ['kind' => $kind]],
                            ['type' => 'text', 'text' => ' '],
                            [
                                'type' => 'parameterReference',
                                'attrs' => [
                                    'ids' => $parameters->pluck('id')->all(),
                                    'label' => $label,
                                ],
                            ],
                            ['type' => 'text', 'text' => ': '.$text],
                        ],
                    ],
                    [
                        'type' => 'reportChart',
                        'attrs' => [
                            'id' => (string) Str::uuid(),
                            'title' => 'Evolução de '.$label,
                            'height' => 300,
                            'startDate' => $startDate->format('Y-m-d'),
                            'endDate' => $endDate->format('Y-m-d'),
                            'teamSlug' => $team->slug,
                            'series' => $series,
                        ],
                    ],
                ],
            ],
            'preview' => $label.': '.$text,
        ];
    }

    private function cleanText(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/```.*?```/s', '', $text) ?? $text;
        $text = preg_replace('/[*_`#]+/', '', $text) ?? $text;

        return trim($text);
    }
}

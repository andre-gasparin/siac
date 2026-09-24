<?php

namespace App\Features\Reports\Services;

use App\Infrastructure\AI\GeminiService;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\ReportSuggestion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReportEditorService
{
    /** @var list<string> */
    private array $allowedNodes = [
        'doc', 'paragraph', 'text', 'hardBreak', 'heading', 'bulletList',
        'orderedList', 'listItem', 'blockquote', 'codeBlock', 'image',
        'reportIcon', 'parameterReference', 'reportChart',
    ];

    public function __construct(
        private ConsolidatedReportService $reports,
        private GeminiService $ai,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function context(Team $team, string $date, int $systemId): array
    {
        $system = $this->system($team, $systemId);
        $report = $this->reportQuery($team, $date)->first();
        $item = $report ? $this->itemQuery($report, $system)->first() : null;

        return [
            'state' => $report === null ? 'new_report' : ($item === null ? 'new_item' : 'existing'),
            'report' => $report ? ['id' => $report->id, 'status' => $report->status, 'date_reference' => $date] : null,
            'item' => $item ? $this->serializeItem($item) : null,
            'system' => ['id' => $system->id, 'name' => $system->name],
            'systems' => $this->systems($team),
            'parameters' => Parameter::query()
                ->whereBelongsTo($team)
                ->whereBelongsTo($system, 'monitoredSystem')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'unit'])
                ->all(),
            'latest_comment' => $this->latestComment($team, $system, $date),
            'phrases' => $report ? $this->phrases($report) : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function save(Team $team, User $user, array $data): array
    {
        $system = $this->system($team, (int) $data['system_id']);
        $this->validateDocument($data['document']);
        $html = $this->sanitizeHtml($data['html']);
        $lockKey = "reports:{$team->id}:{$data['date_reference']}";

        return Cache::lock($lockKey, 15)->block(5, function () use ($team, $user, $system, $data, $html): array {
            return DB::transaction(function () use ($team, $user, $system, $data, $html): array {
                $report = $this->reportQuery($team, $data['date_reference'])->lockForUpdate()->first();

                if (! $report) {
                    $date = Carbon::parse($data['date_reference']);
                    $report = Report::query()->create([
                        'team_id' => $team->id,
                        'created_by' => $user->id,
                        'title' => 'Relatório '.$date->format('d/m/Y'),
                        'date_reference' => $date->toDateString(),
                        'status' => 'draft',
                    ]);
                }

                $item = $this->itemQuery($report, $system)->lockForUpdate()->first();

                if ($item && empty($data['force']) && isset($data['expected_updated_at'])) {
                    $expected = Carbon::parse($data['expected_updated_at'])->utc()->format('Y-m-d H:i:s');
                    $actual = $item->updated_at?->clone()->utc()->format('Y-m-d H:i:s');

                    if ($expected !== $actual) {
                        throw new ConflictHttpException('O comentário foi alterado por outro usuário.');
                    }
                }

                $metadata = $item instanceof ReportItem ? ($item->metadata ?? []) : [];
                $metadata['editor'] = ['schema_version' => 1, 'document' => $data['document']];
                $attributes = [
                    'team_id' => $team->id,
                    'report_id' => $report->id,
                    'monitored_system_id' => $system->id,
                    'date_reference' => Carbon::parse($data['date_reference'])->startOfDay(),
                    'sort_order' => $system->sort_order,
                    'show_data_results' => trim(strip_tags($html)) !== ''
                        ? true
                        : (bool) ($data['show_data_results'] ?? ($item instanceof ReportItem ? $item->show_data_results : false)),
                    'hide_data' => (bool) $data['hide_data'],
                    'is_stopped' => (bool) $data['is_stopped'],
                    'comment' => $html,
                    'metadata' => $metadata,
                ];

                if ($item) {
                    $item->update($attributes);
                } else {
                    $item = ReportItem::query()->create($attributes);
                }

                $this->syncGroupedContent($item, $html, $data['document']);
                $item->refresh();
                $this->reports->revision($item, $user);
                $this->reports->record($report, 'consideration.updated', $user, $item, metadata: [
                    'system_name' => $system->name,
                ]);

                return $this->context($team, $data['date_reference'], $system->id);
            });
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(Team $team, int $systemId, string $beforeDate, ?string $date = null): array
    {
        $system = $this->system($team, $systemId);

        $history = ReportItem::query()
            ->whereBelongsTo($team)
            ->whereBelongsTo($system, 'monitoredSystem')
            ->whereNotNull('comment')
            ->whereHas('report', function (Builder $query) use ($beforeDate, $date): void {
                $query->whereDate('date_reference', '<', $beforeDate)
                    ->when($date, fn (Builder $builder, string $value) => $builder->whereDate('date_reference', $value));
            })
            ->with('report:id,date_reference')
            ->latest('date_reference')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (ReportItem $item): array => $this->serializeHistoryItem($item))
            ->all();

        return array_values($history);
    }

    /**
     * @param  list<int>  $itemIds
     * @return list<array<string, mixed>>
     */
    public function group(Team $team, int $reportId, array $itemIds, int $sourceId): array
    {
        return DB::transaction(function () use ($team, $reportId, $itemIds, $sourceId): array {
            $report = Report::query()->whereBelongsTo($team)->whereKey($reportId)->firstOrFail();
            $items = ReportItem::query()
                ->whereBelongsTo($report)
                ->whereIn('id', $itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->count() !== count($itemIds) || ! in_array($sourceId, $itemIds, true)) {
                throw ValidationException::withMessages(['item_ids' => 'Os itens selecionados são inválidos.']);
            }

            $source = $items->firstWhere('id', $sourceId);
            $source?->update(['parent_report_item_id' => null]);

            foreach ($items as $item) {
                if ($item->id === $sourceId) {
                    continue;
                }

                $metadata = $item->metadata ?? [];
                $metadata['editor'] = $source?->metadata['editor'] ?? null;
                $item->update([
                    'parent_report_item_id' => $sourceId,
                    'comment' => $source?->comment,
                    'metadata' => $metadata,
                ]);
            }

            return $this->phrases($report);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ungroup(Team $team, int $itemId): array
    {
        return DB::transaction(function () use ($team, $itemId): array {
            $item = ReportItem::query()->whereBelongsTo($team)->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $rootId = $item->parent_report_item_id ?: $item->id;

            ReportItem::query()
                ->whereBelongsTo($item->report)
                ->where(fn (Builder $query) => $query->whereKey($rootId)->orWhere('parent_report_item_id', $rootId))
                ->update(['parent_report_item_id' => null]);

            return $this->phrases($item->report);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function phrases(Report $report): array
    {
        $phrases = ReportItem::query()
            ->whereBelongsTo($report)
            ->whereNotNull('monitored_system_id')
            ->with('monitoredSystem:id,name')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ReportItem $item): array => [
                ...$this->serializeItem($item),
                'system_name' => $item->monitoredSystem?->name,
            ])
            ->all();

        return array_values($phrases);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestComment(Team $team, MonitoredSystem $system, string $date): ?array
    {
        $item = ReportItem::query()
            ->whereBelongsTo($team)
            ->whereBelongsTo($system, 'monitoredSystem')
            ->whereNotNull('comment')
            ->whereHas('report', fn (Builder $query) => $query->whereDate('date_reference', '<', $date))
            ->with('report:id,date_reference')
            ->latest('date_reference')
            ->latest('id')
            ->first();

        return $item ? $this->serializeHistoryItem($item) : null;
    }

    /** @return list<ReportSuggestion> */
    public function createSuggestions(Team $team, Report $report, User $user, string $mode, ?string $instruction): array
    {
        $items = ReportItem::query()->whereBelongsTo($report)->whereNotNull('comment')
            ->with('monitoredSystem:id,name')->orderBy('sort_order')->get();
        $payload = $items->map(fn (ReportItem $item): array => [
            'item_id' => $item->id,
            'system' => $item->monitoredSystem?->name,
            'text' => trim(strip_tags((string) $item->comment)),
        ])->filter(fn (array $item): bool => $item['text'] !== '')->values()->all();

        if ($payload === []) {
            throw ValidationException::withMessages(['report' => 'Não há considerações para analisar.']);
        }

        $scope = $mode === 'proofread'
            ? 'Corrija somente ortografia, acentuação, pontuação e concordância em português. Não mude fatos, números, tom ou significado.'
            : 'Atenda à instrução do usuário propondo alterações objetivas sem inventar fatos ou medições.';
        $encodedItems = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response = $this->ai->generateJson(<<<PROMPT
Você revisa considerações Consucal de um relatório operacional. {$scope}
Retorne somente JSON válido no formato:
{"suggestions":[{"item_id":1,"original_text":"trecho exato existente","replacement_text":"substituição","reason":"explicação curta"}]}
Cada original_text deve ser um trecho literal do texto recebido. Gere no máximo 30 sugestões. Não use HTML ou Markdown.
Instrução: {$instruction}
Itens: {$encodedItems}
PROMPT, ['timeout' => 25]);
        $candidates = is_array($response['suggestions'] ?? null) ? array_slice($response['suggestions'], 0, 30) : [];
        $itemMap = $items->keyBy('id');
        $created = [];

        DB::transaction(function () use ($candidates, $itemMap, $team, $report, $user, $mode, &$created): void {
            foreach ($candidates as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                $item = $itemMap->get((int) ($candidate['item_id'] ?? 0));
                $original = trim((string) ($candidate['original_text'] ?? ''));
                $replacement = trim((string) ($candidate['replacement_text'] ?? ''));

                if (! $item || $original === '' || $replacement === '' || $original === $replacement
                    || ! str_contains(trim(strip_tags((string) $item->comment)), $original)) {
                    continue;
                }

                $created[] = ReportSuggestion::query()->create([
                    'team_id' => $team->id,
                    'report_id' => $report->id,
                    'report_item_id' => $item->id,
                    'created_by' => $user->id,
                    'mode' => $mode,
                    'original_text' => $original,
                    'replacement_text' => $replacement,
                    'reason' => mb_substr(trim((string) ($candidate['reason'] ?? '')), 0, 2000),
                    'base_updated_at' => $item->updated_at,
                ]);
            }

            $this->reports->record($report, 'suggestions.created', $user, metadata: [
                'mode' => $mode,
                'count' => count($created),
            ]);
        });

        return $created;
    }

    public function resolveSuggestion(
        Team $team,
        Report $report,
        ReportSuggestion $suggestion,
        User $user,
        string $decision,
    ): ReportSuggestion {
        abort_unless($suggestion->team_id === $team->id && $suggestion->report_id === $report->id, 404);

        return DB::transaction(function () use ($report, $suggestion, $user, $decision): ReportSuggestion {
            $locked = ReportSuggestion::query()->whereKey($suggestion->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                return $locked;
            }

            $item = ReportItem::query()->whereBelongsTo($report)->whereKey($locked->report_item_id)->lockForUpdate()->firstOrFail();

            if ($decision === 'accept') {
                if ($locked->base_updated_at?->utc()->format('Y-m-d H:i:s.u') !== $item->updated_at?->utc()->format('Y-m-d H:i:s.u')) {
                    throw ValidationException::withMessages(['suggestion' => 'A consideração mudou desde a análise. Gere novas sugestões.']);
                }

                if ($locked->original_text !== '' && ! str_contains((string) $item->comment, $locked->original_text)) {
                    throw ValidationException::withMessages(['suggestion' => 'O trecho sugerido não existe mais na consideração.']);
                }

                $metadata = $item->metadata ?? [];
                $metadata['editor']['document'] = null;

                $commentText = trim(strip_tags((string) $item->comment));
                $newComment = ($locked->original_text === '' || $commentText === '')
                    ? '<p>'.e($locked->replacement_text).'</p>'
                    : $this->sanitizeHtml(str_replace(
                        $locked->original_text,
                        e($locked->replacement_text),
                        (string) $item->comment,
                    ));

                $item->update([
                    'comment' => $newComment,
                    'show_data_results' => true,
                    'metadata' => $metadata,
                ]);
                $this->reports->revision($item, $user, 'agent');
            }

            $locked->update([
                'status' => $decision === 'accept' ? 'accepted' : 'rejected',
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);
            $this->reports->record(
                $report,
                $decision === 'accept' ? 'suggestion.accepted' : 'suggestion.rejected',
                $user,
                $item,
                metadata: ['suggestion_id' => $locked->id, 'mode' => $locked->mode],
            );

            return $locked->refresh();
        });
    }

    /**
     * @return list<array{id: int, name: string, sort_order: int}>
     */
    private function systems(Team $team): array
    {
        $systems = MonitoredSystem::query()
            ->whereBelongsTo($team)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order'])
            ->map(fn (MonitoredSystem $system): array => [
                'id' => $system->id,
                'name' => $system->name,
                'sort_order' => $system->sort_order,
            ])->all();

        return array_values($systems);
    }

    private function system(Team $team, int $systemId): MonitoredSystem
    {
        return MonitoredSystem::query()
            ->whereBelongsTo($team)
            ->whereKey($systemId)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /** @return Builder<Report> */
    private function reportQuery(Team $team, string $date): Builder
    {
        return Report::query()
            ->whereBelongsTo($team)
            ->whereDate('date_reference', $date)
            ->orderBy('id');
    }

    /** @return Builder<ReportItem> */
    private function itemQuery(Report $report, MonitoredSystem $system): Builder
    {
        return ReportItem::query()
            ->whereBelongsTo($report)
            ->whereBelongsTo($system, 'monitoredSystem')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(ReportItem $item): array
    {
        $root = $item->parent_report_item_id
            ? ReportItem::query()->find($item->parent_report_item_id)
            : $item;
        $root ??= $item;

        return [
            'id' => $item->id,
            'report_id' => $item->report_id,
            'parent_report_item_id' => $item->parent_report_item_id,
            'document' => $root->metadata['editor']['document'] ?? null,
            'html' => $this->sanitizeHtml((string) ($root->comment ?? '')),
            'show_data_results' => $item->show_data_results,
            'hide_data' => $item->hide_data,
            'is_stopped' => $item->is_stopped,
            'updated_at' => $item->updated_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHistoryItem(ReportItem $item): array
    {
        return [
            'id' => $item->id,
            'date_reference' => $item->report->date_reference?->format('Y-m-d'),
            'document' => $item->metadata['editor']['document'] ?? null,
            'html' => $this->sanitizeHtml((string) $item->comment),
            'preview' => mb_substr(trim(strip_tags((string) $item->comment)), 0, 240),
        ];
    }

    public function sanitizeHtml(string $html): string
    {
        $icons = [
            'ok' => ['kind' => 'success', 'symbol' => '✓'],
            'alert' => ['kind' => 'warning', 'symbol' => '⚠'],
        ];
        $html = preg_replace_callback(
            '/<img\b[^>]*class=(["\'])[^"\']*\bimg-report-(ok|alert)\b[^"\']*\1[^>]*>/i',
            function (array $matches) use ($icons): string {
                $icon = $icons[strtolower($matches[2])];

                return sprintf(
                    '<span class="report-icon report-icon-%s" data-report-icon="%s">%s</span>',
                    $icon['kind'],
                    $icon['kind'],
                    $icon['symbol'],
                );
            },
            $html,
        ) ?? $html;
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowMediaSchemes(['https', 'data'])
            ->allowElement('span', ['class', 'data-report-icon', 'data-parameter-ids'])
            ->allowElement('div', ['class', 'data-report-chart', 'data-chart-id'])
            ->withMaxInputLength(1_000_000);

        return (new HtmlSanitizer($config))->sanitize($html);
    }

    /** @param array<string, mixed> $document */
    public function validateDocument(array $document): void
    {
        $this->validateNode($document, 0);
    }

    /** @param array<string, mixed> $node */
    private function validateNode(array $node, int $depth): void
    {
        if ($depth > 30 || ! isset($node['type']) || ! in_array($node['type'], $this->allowedNodes, true)) {
            throw ValidationException::withMessages(['document' => 'O documento contém uma estrutura não permitida.']);
        }

        if ($node['type'] === 'reportChart') {
            $series = $node['attrs']['series'] ?? [];

            if (! is_array($series) || count($series) < 1 || count($series) > 5) {
                throw ValidationException::withMessages(['document' => 'Cada gráfico deve ter entre uma e cinco séries.']);
            }
        }

        foreach ($node['content'] ?? [] as $child) {
            if (! is_array($child)) {
                throw ValidationException::withMessages(['document' => 'O documento contém conteúdo inválido.']);
            }

            $this->validateNode($child, $depth + 1);
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function syncGroupedContent(ReportItem $item, string $html, array $document): void
    {
        $rootId = $item->parent_report_item_id ?: $item->id;
        $members = ReportItem::query()
            ->whereBelongsTo($item->report)
            ->where(fn (Builder $query) => $query->whereKey($rootId)->orWhere('parent_report_item_id', $rootId))
            ->whereKeyNot($item->id)
            ->lockForUpdate()
            ->get();

        foreach ($members as $member) {
            $metadata = $member->metadata ?? [];
            $metadata['editor'] = ['schema_version' => 1, 'document' => $document];
            $member->update(['comment' => $html, 'metadata' => $metadata]);
        }
    }
}

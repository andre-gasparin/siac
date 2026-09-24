<?php

namespace App\Features\Reports\Services;

use App\Features\DataTable\Services\DataTableDataBuilder;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Report;
use App\Models\ReportActivity;
use App\Models\ReportComment;
use App\Models\ReportItem;
use App\Models\ReportItemRevision;
use App\Models\ReportRecipient;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsolidatedReportService
{
    public function __construct(private DataTableDataBuilder $dataBuilder) {}

    /**
     * @return array{status: string, search: string}
     */
    public function filters(Request $request): array
    {
        $status = $request->string('status')->toString();

        return [
            'status' => in_array($status, ['all', 'draft', 'completed'], true) ? $status : 'all',
            'search' => trim($request->string('search')->toString()),
        ];
    }

    /**
     * @return array{draft: int, completed: int}
     */
    public function statusCounts(Team $team): array
    {
        return [
            'draft' => Report::query()->whereBelongsTo($team)->where('status', 'draft')->count(),
            'completed' => Report::query()->whereBelongsTo($team)->where('status', 'completed')->count(),
        ];
    }

    public function createOrOpen(Team $team, User $user, string $date): Report
    {
        return Cache::lock("reports:{$team->id}:{$date}", 15)->block(5, function () use ($team, $user, $date): Report {
            return DB::transaction(function () use ($team, $user, $date): Report {
                $report = Report::query()
                    ->whereBelongsTo($team)
                    ->whereDate('date_reference', $date)
                    ->lockForUpdate()
                    ->first();

                if ($report) {
                    return $report;
                }

                $reference = Carbon::parse($date);
                $report = Report::query()->create([
                    'team_id' => $team->id,
                    'created_by' => $user->id,
                    'title' => 'Relatório '.$reference->format('d/m/Y'),
                    'date_reference' => $reference->toDateString(),
                    'status' => 'draft',
                ]);
                $this->record($report, 'report.created', $user);

                return $report;
            });
        });
    }

    public function scopedReport(Team $team, int $reportId): Report
    {
        return Report::query()->whereBelongsTo($team)->whereKey($reportId)->firstOrFail();
    }

    /**
     * @param  array{status: string, search: string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(Team $team, array $filters = ['status' => 'all', 'search' => '']): LengthAwarePaginator
    {
        $reports = Report::query()
            ->whereBelongsTo($team)
            ->withCount([
                'items as included_systems_count' => fn ($query) => $query->where('show_data_results', true),
                'recipients as recipients_count' => fn ($query) => $query->whereNull('revoked_at'),
            ])
            ->when(
                $filters['status'] !== 'all',
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = "%{$filters['search']}%";

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', $search)
                        ->orWhere('date_reference', 'like', $search);
                });
            })
            ->latest('date_reference')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return $reports->through(fn (Report $report): array => [
            ...$this->serializeReport($report),
            'included_systems_count' => (int) $report->getAttribute('included_systems_count'),
            'recipients_count' => (int) $report->getAttribute('recipients_count'),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    public function index(Team $team): array
    {
        $reports = $this->paginate($team);

        return ['reports' => [
            'data' => $reports->getCollection()->all(),
            'links' => $reports->linkCollection()->all(),
            'total' => $reports->total(),
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public function view(Team $team, Report $report): array
    {
        $items = ReportItem::query()
            ->whereBelongsTo($report)
            ->with([
                'parentReportItem:id,monitored_system_id',
                'comments' => fn ($query) => $query->with(['user:id,name', 'recipient:id,name,email'])->oldest(),
                'suggestions' => fn ($query) => $query->where('status', 'pending')->oldest(),
            ])
            ->get()
            ->keyBy('monitored_system_id');

        $systems = MonitoredSystem::query()
            ->whereBelongsTo($team)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order']);

        $recipients = $report->recipients()->orderBy('name')->get();
        $recipientsByUser = $recipients->whereNotNull('user_id')->keyBy('user_id');

        return [
            'report' => $this->serializeReport($report),
            'systems' => $systems->map(function (MonitoredSystem $system) use ($items): array {
                /** @var ReportItem|null $item */
                $item = $items->get($system->id);

                return [
                    'id' => $system->id,
                    'name' => $system->name,
                    'sort_order' => $system->sort_order,
                    'item' => $item ? $this->serializeItem($item) : null,
                ];
            })->all(),
            'intendedRecipients' => $team->members()
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(function (User $member) use ($recipientsByUser): array {
                    $recipient = $recipientsByUser->get($member->id);
                    $hasRecipient = $recipient instanceof ReportRecipient;

                    return [
                        'user_id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'recipient_id' => $hasRecipient ? $recipient->id : null,
                        'last_sent_at' => $hasRecipient ? $recipient->last_sent_at?->toIso8601String() : null,
                        'send_count' => $hasRecipient ? $recipient->send_count : 0,
                    ];
                })->all(),
            'recipients' => $recipients->map(fn ($recipient): array => [
                'id' => $recipient->id,
                'user_id' => $recipient->user_id,
                'name' => $recipient->name,
                'email' => $recipient->email,
                'access_token' => $recipient->access_token,
                'public_url' => route('reports.public.show', ['token' => $recipient->access_token]),
                'revoked_at' => $recipient->revoked_at?->toIso8601String(),
                'last_sent_at' => $recipient->last_sent_at?->toIso8601String(),
                'send_count' => $recipient->send_count,
            ])->all(),
            'activities' => $report->activities()
                ->with(['user:id,name', 'recipient:id,name,email'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn ($activity): array => [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'actor' => $activity->user_id
                        ? $activity->user->name
                        : ($activity->report_recipient_id ? $activity->recipient->name : 'Sistema'),
                    'metadata' => $activity->metadata,
                    'created_at' => $activity->created_at?->toIso8601String(),
                ])->all(),
            'existingReportsByDate' => Report::query()
                ->whereBelongsTo($team)
                ->pluck('id', 'date_reference')
                ->mapWithKeys(fn ($id, $date) => [Carbon::parse($date)->format('Y-m-d') => (int) $id])
                ->all(),
        ];
    }

    public function setVisibility(Team $team, Report $report, User $user, int $systemId, bool $show): ReportItem
    {
        $system = MonitoredSystem::query()->whereBelongsTo($team)->whereKey($systemId)->where('is_active', true)->firstOrFail();

        return DB::transaction(function () use ($team, $report, $user, $system, $show): ReportItem {
            $item = ReportItem::query()
                ->whereBelongsTo($report)
                ->whereBelongsTo($system, 'monitoredSystem')
                ->lockForUpdate()
                ->first();
            $hasComment = $item && trim(strip_tags((string) $item->comment)) !== '';
            $visible = $hasComment || $show;

            if (! $item) {
                $item = ReportItem::query()->create([
                    'team_id' => $team->id,
                    'report_id' => $report->id,
                    'monitored_system_id' => $system->id,
                    'date_reference' => $report->date_reference?->startOfDay(),
                    'sort_order' => $system->sort_order,
                    'show_data_results' => $visible,
                ]);
            } else {
                $item->update(['show_data_results' => $visible]);
            }

            $this->revision($item, $user, 'visibility');
            $this->record($report, $visible ? 'system.enabled' : 'system.disabled', $user, $item, metadata: [
                'system_name' => $system->name,
            ]);

            return $item->refresh();
        });
    }

    public function setGroup(
        Team $team,
        Report $report,
        User $user,
        int $systemId,
        ?int $parentSystemId,
        bool $clearComment = false,
    ): ReportItem {
        $system = MonitoredSystem::query()
            ->whereBelongsTo($team)
            ->whereKey($systemId)
            ->where('is_active', true)
            ->firstOrFail();

        $parentItem = null;
        if ($parentSystemId !== null) {
            $parentSystem = MonitoredSystem::query()
                ->whereBelongsTo($team)
                ->whereKey($parentSystemId)
                ->where('is_active', true)
                ->firstOrFail();

            $parentItem = ReportItem::query()
                ->whereBelongsTo($report)
                ->whereBelongsTo($parentSystem, 'monitoredSystem')
                ->firstOrCreate([
                    'team_id' => $team->id,
                    'report_id' => $report->id,
                    'monitored_system_id' => $parentSystem->id,
                ], [
                    'date_reference' => $report->date_reference?->startOfDay(),
                    'sort_order' => $parentSystem->sort_order,
                    'show_data_results' => true,
                ]);

            if (! $parentItem->show_data_results) {
                $parentItem->update(['show_data_results' => true]);
            }
        }

        return DB::transaction(function () use ($team, $report, $user, $system, $parentItem, $clearComment): ReportItem {
            $item = ReportItem::query()
                ->whereBelongsTo($report)
                ->whereBelongsTo($system, 'monitoredSystem')
                ->lockForUpdate()
                ->first();

            if (! $item) {
                $item = ReportItem::query()->create([
                    'team_id' => $team->id,
                    'report_id' => $report->id,
                    'monitored_system_id' => $system->id,
                    'parent_report_item_id' => $parentItem?->id,
                    'date_reference' => $report->date_reference?->startOfDay(),
                    'sort_order' => $system->sort_order,
                    'show_data_results' => true,
                    'comment' => null,
                ]);
            } else {
                $updates = [
                    'parent_report_item_id' => $parentItem?->id,
                    'show_data_results' => true,
                ];
                if ($clearComment) {
                    $updates['comment'] = null;
                }
                $item->update($updates);
            }

            $this->revision($item, $user, 'grouping');
            $this->record(
                $report,
                $parentItem ? 'system.grouped' : 'system.ungrouped',
                $user,
                $item,
                metadata: [
                    'system_name' => $system->name,
                    'parent_system_id' => $parentItem?->monitored_system_id,
                ],
            );

            return $item->refresh();
        });
    }

    public function revision(ReportItem $item, ?User $user, string $source = 'manual'): ReportItemRevision
    {
        return ReportItemRevision::query()->create([
            'report_item_id' => $item->id,
            'user_id' => $user?->id,
            'source' => $source,
            'document' => $item->metadata['editor']['document'] ?? null,
            'html' => $item->comment,
            'show_data_results' => (bool) ($item->show_data_results ?? false),
            'hide_data' => (bool) ($item->hide_data ?? false),
            'is_stopped' => (bool) ($item->is_stopped ?? false),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Report $report,
        string $action,
        ?User $user = null,
        ?ReportItem $item = null,
        ?ReportRecipient $recipient = null,
        array $metadata = [],
    ): ReportActivity {
        return ReportActivity::query()->create([
            'team_id' => $report->team_id,
            'report_id' => $report->id,
            'report_item_id' => $item?->id,
            'user_id' => $user?->id,
            'report_recipient_id' => $recipient?->id,
            'action' => $action,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * @param  array{search: string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateUserReports(Team $team, array $filters = ['search' => '']): LengthAwarePaginator
    {
        $reports = Report::query()
            ->whereBelongsTo($team)
            ->where('status', 'completed')
            ->withCount([
                'items as included_systems_count' => fn ($query) => $query->where('show_data_results', true),
            ])
            ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters): void {
                $search = "%{$filters['search']}%";

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', $search)
                        ->orWhere('date_reference', 'like', $search);
                });
            })
            ->latest('date_reference')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return $reports->through(fn (Report $report): array => [
            'id' => $report->id,
            'title' => $report->title,
            'status' => $report->status,
            'date_reference' => $report->date_reference?->format('Y-m-d'),
            'finished_at' => $report->finished_at?->toIso8601String(),
            'included_systems_count' => (int) $report->getAttribute('included_systems_count'),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    public function dataForSystem(
        Team $team,
        Report $report,
        MonitoredSystem $system,
        ?string $startDate = null,
        ?string $endDate = null,
    ): array {
        abort_unless($report->team_id === $team->id && $system->team_id === $team->id, 404);

        $end = $endDate ?? $report->date_reference?->format('Y-m-d') ?? Carbon::now()->format('Y-m-d');
        $start = $startDate ?? Carbon::parse($end)->subDays(6)->format('Y-m-d');

        $systemIds = $this->resolveSystemIdsWithGrouped($report, $system->id);

        $data = $this->dataBuilder->build($team, [
            'system_ids' => $systemIds,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        if (count($systemIds) > 1 && isset($data['parameters'])) {
            $paramsArray = $data['parameters'] instanceof Collection
                ? $data['parameters']->all()
                : (array) $data['parameters'];
            $data['parameters'] = $this->sortParametersForGroupedSystems($paramsArray, $system->id);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function dailyDataForSystem(Team $team, Report $report, MonitoredSystem $system): array
    {
        abort_unless($report->team_id === $team->id && $system->team_id === $team->id, 404);

        $date = $report->date_reference?->format('Y-m-d') ?? Carbon::now()->format('Y-m-d');

        $systemIds = $this->resolveSystemIdsWithGrouped($report, $system->id);

        $data = $this->dataBuilder->build($team, [
            'system_ids' => $systemIds,
            'start_date' => $date,
            'end_date' => $date,
        ]);

        if (count($systemIds) > 1 && isset($data['parameters'])) {
            $paramsArray = $data['parameters'] instanceof Collection
                ? $data['parameters']->all()
                : (array) $data['parameters'];
            $data['parameters'] = $this->sortParametersForGroupedSystems($paramsArray, $system->id);
        }

        return $data;
    }

    /**
     * @return list<int>
     */
    private function resolveSystemIdsWithGrouped(Report $report, int $primarySystemId): array
    {
        $systemIds = [$primarySystemId];
        $item = ReportItem::query()
            ->whereBelongsTo($report)
            ->where('monitored_system_id', $primarySystemId)
            ->first();

        if ($item) {
            $childSystemIds = ReportItem::query()
                ->whereBelongsTo($report)
                ->where('parent_report_item_id', $item->id)
                ->whereNotNull('monitored_system_id')
                ->pluck('monitored_system_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($childSystemIds !== []) {
                $systemIds = array_values(array_unique(array_merge($systemIds, $childSystemIds)));
            }
        }

        return $systemIds;
    }

    /**
     * @param  array<int, array<string, mixed>>  $parameters
     * @return array<int, array<string, mixed>>
     */
    private function sortParametersForGroupedSystems(array $parameters, int $primarySystemId): array
    {
        $primaryParams = [];
        $childParams = [];

        foreach ($parameters as $param) {
            if ((int) ($param['monitored_system_id'] ?? 0) === $primarySystemId) {
                $primaryParams[] = $param;
            } else {
                $childParams[] = $param;
            }
        }

        usort($primaryParams, fn (array $a, array $b): int => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0)
            ?: strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        $ordered = [];
        $placedChildIds = [];

        foreach ($primaryParams as $pParam) {
            $ordered[] = $pParam;
            $normName = mb_strtolower(trim((string) ($pParam['name'] ?? '')));

            foreach ($childParams as $cParam) {
                $cId = $cParam['id'];
                if (! in_array($cId, $placedChildIds, true)) {
                    $cNormName = mb_strtolower(trim((string) ($cParam['name'] ?? '')));
                    if ($cNormName === $normName) {
                        $ordered[] = $cParam;
                        $placedChildIds[] = $cId;
                    }
                }
            }
        }

        foreach ($childParams as $cParam) {
            $cId = $cParam['id'];
            if (! in_array($cId, $placedChildIds, true)) {
                $ordered[] = $cParam;
                $placedChildIds[] = $cId;
            }
        }

        return $ordered;
    }

    /** @return array<string, mixed> */
    public function userView(Team $team, Report $report, ?User $user = null): array
    {
        abort_unless($report->team_id === $team->id, 404);

        return $this->buildClientReportView(
            team: $team,
            report: $report,
            currentRecipient: null,
            currentUser: $user,
            isPreview: false,
        );
    }

    /** @return array<string, mixed> */
    public function chart(Team $team, Report $report, MonitoredSystem $system, int $parameterId, string $type): array
    {
        abort_unless($report->team_id === $team->id && $system->team_id === $team->id, 404);

        $parameter = Parameter::query()
            ->whereBelongsTo($team)
            ->whereBelongsTo($system, 'monitoredSystem')
            ->whereKey($parameterId)
            ->where('is_active', true)
            ->firstOrFail();
        $end = $report->date_reference?->endOfDay() ?? Carbon::now()->endOfDay();
        $start = $end->copy()->subDays(6)->startOfDay();
        $points = ParameterValue::query()
            ->whereBelongsTo($team)
            ->whereBelongsTo($system, 'monitoredSystem')
            ->whereBelongsTo($parameter)
            ->whereBetween('measured_at', [$start, $end])
            ->orderBy('measured_at')
            ->get(['measured_at', 'value'])
            ->map(fn (ParameterValue $value): array => [
                'timestamp' => $value->measured_at->toIso8601String(),
                'formatted_time' => $value->measured_at->format('d/m/Y H:i'),
                'value' => (float) $value->value,
            ])->all();

        return ['series' => [[
            'parameter_id' => $parameter->id,
            'label' => $parameter->name,
            'chart_type' => $type,
            'color' => '#2563eb',
            'stroke_width' => 2,
            'axis_position' => 'left',
            'unit' => $parameter->unit,
            'show_points' => true,
            'show_values' => false,
            'data' => $points,
        ]]];
    }

    /**
     * @param  list<array<string, mixed>>  $series
     * @return array{series: list<array<string, mixed>>}
     */
    public function chartData(Team $team, string $startDate, string $endDate, array $series): array
    {
        $parameterIds = collect($series)->pluck('parameter_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $parameters = Parameter::query()
            ->whereBelongsTo($team)
            ->whereIn('id', $parameterIds)
            ->get(['id', 'name', 'unit', 'decimals'])
            ->keyBy('id');

        if ($parameters->count() !== $parameterIds->count()) {
            throw ValidationException::withMessages(['series' => 'Um ou mais parâmetros não pertencem a esta unidade.']);
        }

        $values = ParameterValue::query()
            ->whereBelongsTo($team)
            ->whereIn('parameter_id', $parameterIds)
            ->whereBetween('measured_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->oldest('measured_at')
            ->get(['parameter_id', 'measured_at', 'value'])
            ->groupBy('parameter_id');

        $builtSeries = collect($series)->map(function (array $config) use ($parameters, $values): array {
            $parameter = $parameters->get((int) $config['parameter_id']);

            return [
                ...$config,
                'label' => $config['label'] ?? $parameter->name,
                'unit' => $parameter->unit,
                'data' => $values->get($parameter->id, collect())->map(fn (ParameterValue $value): array => [
                    'timestamp' => $value->measured_at->toIso8601String(),
                    'formatted_time' => $value->measured_at->format('d/m H:i'),
                    'value' => $value->value,
                ])->values()->all(),
            ];
        })->all();

        return ['series' => array_values($builtSeries)];
    }

    public function recipient(string $token): ReportRecipient
    {
        $recipient = ReportRecipient::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->with(['report.team'])
            ->firstOrFail();

        abort_unless($recipient->report->status === 'completed', 404);
        abort_unless($recipient->user_id !== null && $recipient->report->team->members()->whereKey($recipient->user_id)->exists(), 403);

        return $recipient;
    }

    /** @return array<string, mixed> */
    public function publicView(ReportRecipient $recipient): array
    {
        return $this->buildClientReportView(
            team: $recipient->report->team,
            report: $recipient->report,
            currentRecipient: $recipient,
            currentUser: null,
            isPreview: false,
        );
    }

    /** @return array<string, mixed> */
    public function previewView(Team $team, Report $report, User $adminUser): array
    {
        abort_unless($report->team_id === $team->id, 404);

        return $this->buildClientReportView(
            team: $team,
            report: $report,
            currentRecipient: null,
            currentUser: $adminUser,
            isPreview: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildClientReportView(
        Team $team,
        Report $report,
        ?ReportRecipient $currentRecipient = null,
        ?User $currentUser = null,
        bool $isPreview = false,
    ): array {
        $items = ReportItem::query()
            ->whereBelongsTo($report)
            ->where('show_data_results', true)
            ->whereNull('parent_report_item_id')
            ->with([
                'monitoredSystem:id,team_id,name,sort_order',
                'childReportItems.monitoredSystem:id,team_id,name',
                'comments' => fn ($query) => $query->with(['user:id,name,is_admin', 'recipient:id,name,email'])->oldest(),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $dataBySystem = $items->mapWithKeys(function (ReportItem $item) use ($team, $report): array {
            $system = $item->monitoredSystem;

            return $system ? [$system->id => $this->dailyDataForSystem($team, $report, $system)] : [];
        })->all();

        $recipients = $report->recipients()
            ->whereNull('revoked_at')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'last_sent_at', 'send_count'])
            ->map(fn (ReportRecipient $recipient): array => [
                'id' => $recipient->id,
                'name' => $recipient->name,
                'email' => $recipient->email,
                'last_sent_at' => $recipient->last_sent_at?->toIso8601String(),
                'send_count' => $recipient->send_count,
            ])->all();

        $recipientInfo = null;
        if ($currentRecipient) {
            $recipientInfo = [
                'name' => $currentRecipient->name,
                'email' => $currentRecipient->email,
                'is_admin' => false,
            ];
        } elseif ($currentUser) {
            $recipientInfo = [
                'name' => $currentUser->name,
                'email' => $currentUser->email,
                'is_admin' => (bool) $currentUser->is_admin,
            ];
        }

        return [
            'report' => [
                'id' => $report->id,
                'title' => $report->title,
                'status' => $report->status,
                'date_reference' => $report->date_reference?->format('Y-m-d'),
                'team_name' => $team->name,
                'finished_at' => $report->finished_at?->toIso8601String(),
            ],
            'isPreview' => $isPreview,
            'recipient' => $recipientInfo,
            'recipients' => $recipients,
            'dataBySystem' => $dataBySystem,
            'systems' => $items->map(function (ReportItem $item): array {
                $combinedTitle = $this->formatCombinedSystemTitle(
                    $item->monitoredSystem?->name ?? 'Sistema',
                    $item->childReportItems,
                );

                return [
                    'id' => $item->monitored_system_id,
                    'name' => $combinedTitle,
                    'sort_order' => $item->sort_order,
                    'item' => [
                        'id' => $item->id,
                        'show_data_results' => $item->show_data_results,
                        'hide_data' => $item->hide_data,
                        'is_stopped' => $item->is_stopped,
                        'comment' => $item->comment,
                        'comments' => $item->comments->map(fn (ReportComment $comment): array => [
                            'id' => $comment->id,
                            'author' => $comment->user_id
                                ? $comment->user->name
                                : ($comment->report_recipient_id ? $comment->recipient->name : 'Destinatário'),
                            'body' => $comment->body,
                            'is_consucal' => $comment->user_id !== null && (bool) $comment->user?->is_admin,
                            'created_at' => $comment->created_at?->toIso8601String(),
                        ])->all(),
                    ],
                ];
            })->all(),
        ];
    }

    /**
     * @param  Collection<int, ReportItem>  $children
     */
    private function formatCombinedSystemTitle(string $parentName, Collection $children): string
    {
        if ($children->isEmpty()) {
            return $parentName;
        }

        $names = array_merge(
            [$parentName],
            $children->map(fn (ReportItem $child) => $child->monitoredSystem?->name)->filter()->values()->all()
        );

        if (count($names) <= 1) {
            return $names[0] ?? $parentName;
        }

        if (count($names) === 2) {
            return "{$names[0]} e {$names[1]}";
        }

        $last = array_pop($names);

        return implode(', ', $names).' e '.$last;
    }

    public function recordPublicAccess(ReportRecipient $recipient): void
    {
        $recipient->update(['last_accessed_at' => now()]);
        $this->record($recipient->report, 'public.accessed', recipient: $recipient);
    }

    public function addPublicComment(ReportRecipient $recipient, int $itemId, string $body): ReportComment
    {
        $item = ReportItem::query()
            ->whereBelongsTo($recipient->report)
            ->whereKey($itemId)
            ->where('show_data_results', true)
            ->firstOrFail();

        $userId = $recipient->user_id ?? auth()->id();

        $comment = ReportComment::query()->create([
            'team_id' => $recipient->team_id,
            'report_id' => $recipient->report_id,
            'report_item_id' => $item->id,
            'report_recipient_id' => $recipient->id,
            'user_id' => $userId,
            'body' => trim($body),
        ]);
        $this->record($recipient->report, 'comment.created', item: $item, recipient: $recipient, metadata: [
            'comment_id' => $comment->id,
        ]);

        return $comment;
    }

    public function addComment(Team $team, Report $report, User $user, int $itemId, string $body): ReportComment
    {
        $item = ReportItem::query()->whereBelongsTo($report)->whereKey($itemId)->firstOrFail();

        $recipient = ReportRecipient::query()
            ->whereBelongsTo($report)
            ->where('user_id', $user->id)
            ->first();

        $comment = ReportComment::query()->create([
            'team_id' => $team->id,
            'report_id' => $report->id,
            'report_item_id' => $item->id,
            'user_id' => $user->id,
            'report_recipient_id' => $recipient?->id,
            'body' => trim($body),
        ]);
        $this->record($report, 'comment.created', $user, $item, metadata: ['comment_id' => $comment->id]);

        return $comment;
    }

    public function isPubliclyVisible(ReportItem $item): bool
    {
        return (bool) $item->show_data_results;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReport(Report $report): array
    {
        return [
            'id' => $report->id,
            'title' => $report->title,
            'status' => $report->status,
            'date_reference' => $report->date_reference?->format('Y-m-d'),
            'finished_at' => $report->finished_at?->toIso8601String(),
            'emailed_at' => $report->emailed_at?->toIso8601String(),
            'email_count' => $report->email_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(ReportItem $item): array
    {
        return [
            'id' => $item->id,
            'parent_report_item_id' => $item->parent_report_item_id,
            'parent_monitored_system_id' => $item->parentReportItem?->monitored_system_id,
            'show_data_results' => $item->show_data_results,
            'hide_data' => $item->hide_data,
            'is_stopped' => $item->is_stopped,
            'comment' => $item->comment,
            'updated_at' => $item->updated_at?->toIso8601String(),
            'comments' => $item->comments->map(fn ($comment): array => [
                'id' => $comment->id,
                'author' => $comment->user_id
                    ? $comment->user->name
                    : ($comment->report_recipient_id ? $comment->recipient->name : 'Destinatário'),
                'body' => $comment->body,
                'is_consucal' => $comment->user_id !== null,
                'created_at' => $comment->created_at?->toIso8601String(),
            ])->all(),
            'suggestions' => $item->suggestions->map(fn ($suggestion): array => [
                'id' => $suggestion->id,
                'mode' => $suggestion->mode,
                'original_text' => $suggestion->original_text,
                'replacement_text' => $suggestion->replacement_text,
                'reason' => $suggestion->reason,
            ])->all(),
        ];
    }
}

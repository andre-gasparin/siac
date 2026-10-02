<?php

namespace App\Features\DataEntry\Http\Controllers;

use App\Features\DataEntry\Actions\RevertDataEntryBatchAction;
use App\Features\DataEntry\Actions\RevertDataEntryBatchGroupAction;
use App\Http\Controllers\Controller;
use App\Models\DataEntryBatch;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataEntryHistoryController extends Controller
{
    public function index(Request $request, Team $current_team): JsonResponse
    {
        $systemId = $request->filled('system_id') ? (int) $request->input('system_id') : null;
        $status = $request->input('status', 'all');

        $groupsQuery = DataEntryBatch::query()
            ->where('team_id', $current_team->id)
            ->when($systemId, fn ($q) => $q->where('monitored_system_id', $systemId))
            ->when($status === 'completed' || $status === 'reverted', fn ($q) => $q->where('status', $status))
            ->select('batch_group_uuid', DB::raw('MAX(id) as max_id'))
            ->groupBy('batch_group_uuid')
            ->orderByDesc('max_id');

        $paginator = $groupsQuery->paginate(10);

        $groupUuids = collect($paginator->items())->pluck('batch_group_uuid')->filter()->all();

        $batchesByGroup = ! empty($groupUuids)
            ? DataEntryBatch::query()
                ->where('team_id', $current_team->id)
                ->whereIn('batch_group_uuid', $groupUuids)
                ->with([
                    'user:id,name',
                    'revertedBy:id,name',
                    'monitoredSystem:id,name',
                ])
                ->orderByDesc('id')
                ->get()
                ->groupBy('batch_group_uuid')
            : collect();

        $currentUser = $request->user();

        $items = collect($groupUuids)->map(function (string $groupUuid) use ($batchesByGroup, $currentUser): array {
            $groupBatches = $batchesByGroup->get($groupUuid, collect());
            $firstBatch = $groupBatches->first();

            $completedCount = $groupBatches->where('status', 'completed')->count();
            $revertedCount = $groupBatches->where('status', 'reverted')->count();

            $groupStatus = 'completed';
            if ($completedCount === 0 && $revertedCount > 0) {
                $groupStatus = 'reverted';
            } elseif ($completedCount > 0 && $revertedCount > 0) {
                $groupStatus = 'partial';
            }

            $revertedBatch = $groupBatches->where('status', 'reverted')->sortByDesc('reverted_at')->first();

            $systems = $groupBatches->map(function (DataEntryBatch $batch) use ($currentUser): array {
                return [
                    'id' => $batch->id,
                    'monitored_system_id' => $batch->monitored_system_id,
                    'system_name' => $batch->monitoredSystem->name ?? "Sistema #{$batch->monitored_system_id}",
                    'status' => $batch->status,
                    'saved_values_count' => $batch->saved_values_count,
                    'comment' => $batch->comment,
                    'reverted_at' => $batch->reverted_at ? $batch->reverted_at->format('d/m/Y H:i:s') : null,
                    'reverted_by_name' => $batch->revertedBy->name ?? null,
                    'can_revert' => (bool) $currentUser->is_admin || ($batch->user_id === $currentUser->id),
                ];
            })->values()->all();

            $systemsNames = $groupBatches->map(fn (DataEntryBatch $b) => $b->monitoredSystem->name ?? "Sistema #{$b->monitored_system_id}")->unique()->values()->all();

            return [
                'batch_group_uuid' => $groupUuid,
                'created_at' => $firstBatch?->created_at ? $firstBatch->created_at->format('d/m/Y H:i:s') : null,
                'collected_at' => $firstBatch?->collected_at ? $firstBatch->collected_at->format('d/m/Y H:i') : null,
                'responsible' => $firstBatch?->responsible ?: ($firstBatch?->user?->name ?? 'Usuário'),
                'user_name' => $firstBatch?->user?->name ?? 'Usuário',
                'user_id' => $firstBatch?->user_id,
                'status' => $groupStatus,
                'systems_count' => count($systemsNames),
                'systems_names' => $systemsNames,
                'systems' => $systems,
                'total_values_count' => $groupBatches->sum('saved_values_count'),
                'reverted_at' => $revertedBatch?->reverted_at ? $revertedBatch->reverted_at->format('d/m/Y H:i:s') : null,
                'reverted_by_name' => $revertedBatch?->revertedBy?->name ?? null,
                'can_revert' => (bool) $currentUser->is_admin || $groupBatches->contains(fn ($b) => $b->user_id === $currentUser->id),
            ];
        });

        return response()->json([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    public function showGroup(Request $request, Team $current_team, string $batch_group_uuid): JsonResponse
    {
        $batches = DataEntryBatch::query()
            ->where('team_id', $current_team->id)
            ->where('batch_group_uuid', $batch_group_uuid)
            ->with([
                'user:id,name',
                'revertedBy:id,name',
                'monitoredSystem:id,name',
            ])
            ->orderBy('id')
            ->get();

        if ($batches->isEmpty()) {
            return response()->json(['message' => 'Envio não encontrado.'], 404);
        }

        $allParamIds = [];
        $activeBatches = [];

        foreach ($batches as $batch) {
            if ($batch->status === 'completed' && ! empty($batch->parameter_ids)) {
                $allParamIds = array_merge($allParamIds, $batch->parameter_ids);
                $activeBatches[] = $batch;
            }
        }

        $allParamIds = array_values(array_unique($allParamIds));

        $parametersMap = ! empty($allParamIds)
            ? Parameter::query()
                ->where('team_id', $current_team->id)
                ->whereIn('id', $allParamIds)
                ->get()
                ->keyBy('id')
            : collect();

        $valuesMap = collect();
        if (! empty($activeBatches) && ! empty($allParamIds)) {
            $valuesMap = ParameterValue::query()
                ->where('team_id', $current_team->id)
                ->whereIn('parameter_id', $allParamIds)
                ->get()
                ->groupBy(fn (ParameterValue $pv) => "{$pv->monitored_system_id}_{$pv->measured_at->toDateTimeString()}");
        }

        $currentUser = $request->user();

        $systemsData = $batches->map(function (DataEntryBatch $batch) use ($parametersMap, $valuesMap, $currentUser): array {
            $details = [];

            if ($batch->status === 'reverted' && ! empty($batch->snapshot)) {
                $details = $batch->snapshot;
            } elseif (! empty($batch->parameter_ids)) {
                $key = "{$batch->monitored_system_id}_{$batch->collected_at->toDateTimeString()}";
                $batchValues = $valuesMap->get($key, collect())->keyBy('parameter_id');

                foreach ($batch->parameter_ids as $paramId) {
                    $param = $parametersMap->get($paramId);
                    $valRecord = $batchValues->get($paramId);

                    $details[] = [
                        'parameter_id' => $paramId,
                        'name' => $param ? $param->name : "Parâmetro #{$paramId}",
                        'code' => $param?->code,
                        'unit' => $param?->unit,
                        'decimals' => $param ? $param->decimals : 2,
                        'value' => $valRecord ? $valRecord->value : null,
                        'alert_1_min' => $param?->alert_1_min,
                        'alert_1_max' => $param?->alert_1_max,
                    ];
                }
            }

            return [
                'id' => $batch->id,
                'monitored_system_id' => $batch->monitored_system_id,
                'system_name' => $batch->monitoredSystem->name ?? "Sistema #{$batch->monitored_system_id}",
                'user_name' => $batch->user->name ?? 'Usuário',
                'user_id' => $batch->user_id,
                'responsible' => $batch->responsible ?: ($batch->user->name ?? 'Usuário'),
                'status' => $batch->status,
                'collected_at' => $batch->collected_at->format('d/m/Y H:i'),
                'created_at' => $batch->created_at ? $batch->created_at->format('d/m/Y H:i:s') : null,
                'reverted_at' => $batch->reverted_at ? $batch->reverted_at->format('d/m/Y H:i:s') : null,
                'reverted_by_name' => $batch->revertedBy->name ?? null,
                'saved_values_count' => $batch->saved_values_count,
                'comment' => $batch->comment,
                'parameters_data' => $details,
                'can_revert' => (bool) $currentUser->is_admin || ($batch->user_id === $currentUser->id),
            ];
        });

        return response()->json([
            'batch_group_uuid' => $batch_group_uuid,
            'systems' => $systemsData,
        ]);
    }

    public function destroyGroup(
        Request $request,
        Team $current_team,
        string $batch_group_uuid,
        RevertDataEntryBatchGroupAction $action,
    ): JsonResponse {
        $result = $action->execute($current_team, $request->user(), $batch_group_uuid);

        return response()->json([
            'success' => true,
            'message' => "Envio excluído e medições de {$result['reverted_count']} sistema(s) revertidas com sucesso!",
            'batch_group_uuid' => $batch_group_uuid,
            'reverted_count' => $result['reverted_count'],
        ]);
    }

    public function destroy(Request $request, Team $current_team, DataEntryBatch $batch, RevertDataEntryBatchAction $action): JsonResponse
    {
        $revertedBatch = $action->execute($current_team, $request->user(), $batch);

        return response()->json([
            'success' => true,
            'message' => 'Envio do sistema excluído e medições revertidas com sucesso!',
            'batch_id' => $revertedBatch->id,
        ]);
    }
}

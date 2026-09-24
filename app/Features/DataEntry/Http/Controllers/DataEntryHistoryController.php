<?php

namespace App\Features\DataEntry\Http\Controllers;

use App\Features\DataEntry\Actions\RevertDataEntryBatchAction;
use App\Http\Controllers\Controller;
use App\Models\DataEntryBatch;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataEntryHistoryController extends Controller
{
    public function index(Request $request, Team $current_team): JsonResponse
    {
        $systemId = $request->filled('system_id') ? (int) $request->input('system_id') : null;
        $status = $request->input('status', 'all');

        $query = DataEntryBatch::query()
            ->where('team_id', $current_team->id)
            ->with([
                'user:id,name',
                'revertedBy:id,name',
                'monitoredSystem:id,name',
            ])
            ->when($systemId, fn ($q) => $q->where('monitored_system_id', $systemId))
            ->when($status === 'completed' || $status === 'reverted', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id');

        $paginator = $query->paginate(10);

        // Pre-fetch parameters and active values for active batches on current page
        $allParamIds = [];
        $activeBatches = [];

        foreach ($paginator->items() as $batch) {
            if ($batch->status === 'completed' && ! empty($batch->parameter_ids)) {
                $allParamIds = array_merge($allParamIds, $batch->parameter_ids);
                $activeBatches[] = $batch;
            }
        }

        $allParamIds = array_unique($allParamIds);

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

        $items = collect($paginator->items())->map(function (DataEntryBatch $batch) use ($parametersMap, $valuesMap, $currentUser): array {
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
                'system_name' => $batch->monitoredSystem->name ?? 'Sistema',
                'monitored_system_id' => $batch->monitored_system_id,
                'user_name' => $batch->user->name ?? 'Usuário',
                'user_id' => $batch->user_id,
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
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    public function destroy(Request $request, Team $current_team, DataEntryBatch $batch, RevertDataEntryBatchAction $action): JsonResponse
    {
        $revertedBatch = $action->execute($current_team, $request->user(), $batch);

        return response()->json([
            'success' => true,
            'message' => 'Envio excluído e medições revertidas com sucesso!',
            'batch_id' => $revertedBatch->id,
        ]);
    }
}

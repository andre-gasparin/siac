<?php

namespace App\Features\DataTable\Http\Controllers;

use App\Features\DataTable\Http\Requests\DataTableRowDetailsRequest;
use App\Features\DataTable\Services\DataTableMutationService;
use App\Http\Controllers\Controller;
use App\Models\DataEntryBatch;
use App\Models\MonitoredSystem;
use App\Models\ParameterValue;
use App\Models\ParameterValueRevision;
use App\Models\ParameterValuesComment;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DataTableRowDetailsController extends Controller
{
    public function show(DataTableRowDetailsRequest $request, Team $current_team): JsonResponse
    {
        $at = Carbon::parse($request->validated('timestamp'));
        $systemIds = $request->validated('system_ids');
        $systems = MonitoredSystem::query()->where('team_id', $current_team->id)->whereIn('id', $systemIds)->get(['id', 'name']);
        $comments = ParameterValuesComment::query()->where('team_id', $current_team->id)->whereIn('monitored_system_id', $systemIds)->where('measured_at', $at)->with('creator:id,name')->get()->keyBy('monitored_system_id');
        $history = ParameterValueRevision::query()->where('team_id', $current_team->id)->whereIn('monitored_system_id', $systemIds)->where('measured_at', $at)->with('user:id,name')->orderByDesc('id')->get()->map(fn ($revision): array => [
            'id' => 'revision-'.$revision->id, 'system_id' => $revision->monitored_system_id, 'action' => $revision->action, 'user' => $revision->user?->name, 'created_at' => $revision->created_at?->toDateTimeString(), 'changes' => $revision->changes,
        ]);
        $batches = DataEntryBatch::query()->where('team_id', $current_team->id)->whereIn('monitored_system_id', $systemIds)->where('collected_at', $at)->with('user:id,name')->get()->map(fn ($batch): array => [
            'id' => 'batch-'.$batch->id, 'system_id' => $batch->monitored_system_id, 'action' => 'envio', 'user' => $batch->user?->name, 'created_at' => $batch->created_at?->toDateTimeString(), 'changes' => ['count' => $batch->saved_values_count, 'responsible' => $batch->responsible, 'status' => $batch->status],
        ]);
        $readings = ParameterValue::query()->where('team_id', $current_team->id)->whereIn('monitored_system_id', $systemIds)->where('measured_at', $at)->with(['creator:id,name', 'updater:id,name', 'parameter:id,name'])->get()->map(fn ($value): array => [
            'parameter' => $value->parameter->name, 'value' => $value->value, 'creator' => $value->creator?->name, 'updater' => $value->updater?->name, 'created_at' => $value->created_at?->toDateTimeString(), 'updated_at' => $value->updated_at?->toDateTimeString(),
        ]);

        return response()->json([
            'comments' => $systems->map(fn ($system): array => ['system_id' => $system->id, 'system_name' => $system->name, 'comment' => isset($comments[$system->id]) ? $comments[$system->id]->comment : '', 'author' => $comments->get($system->id)?->creator?->name]),
            'history' => $history->concat($batches)->sortByDesc('created_at')->values(),
            'readings' => $readings,
        ]);
    }

    public function updateComment(DataTableRowDetailsRequest $request, Team $current_team, DataTableMutationService $mutations): JsonResponse
    {
        $mutations->updateComment($current_team, $request->user(), (int) $request->validated('monitored_system_id'), Carbon::parse($request->validated('timestamp')), $request->validated('comment'));

        return response()->json(['success' => true]);
    }
}

<?php

namespace App\Features\DataEntry\Http\Controllers;

use App\Features\DataEntry\Actions\FetchDataEntryEntriesAction;
use App\Features\DataEntry\Actions\StoreBatchDataEntryAction;
use App\Features\DataEntry\Actions\StoreDataEntryAction;
use App\Features\DataEntry\Http\Requests\StoreBatchDataEntryRequest;
use App\Features\DataEntry\Http\Requests\StoreDataEntryRequest;
use App\Http\Controllers\Controller;
use App\Models\MonitoredSystem;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DataEntryController extends Controller
{
    public function index(Request $request, Team $current_team, FetchDataEntryEntriesAction $fetchAction): Response
    {
        $systems = MonitoredSystem::query()
            ->where('team_id', $current_team->id)
            ->where('is_active', true)
            ->with([
                'parameters' => function ($query): void {
                    $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->select([
                            'id',
                            'monitored_system_id',
                            'team_id',
                            'name',
                            'code',
                            'tag',
                            'unit',
                            'decimals',
                            'sort_order',
                            'alert_1_min',
                            'alert_1_max',
                        ]);
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'team_id', 'name', 'sort_order']);

        $defaultCollectionDate = Carbon::now()->format('Y-m-d');
        $defaultCollectionTime = Carbon::now()->format('H:i');
        $initialEntries = $fetchAction->execute(
            $current_team,
            "{$defaultCollectionDate} {$defaultCollectionTime}:00",
        );

        return Inertia::render('DataEntry/Index', [
            'systems' => $systems,
            'currentTeam' => $current_team,
            'currentTimestamp' => Carbon::now()->format('d/m/Y H:i:s'),
            'defaultCollectionDate' => $defaultCollectionDate,
            'defaultCollectionTime' => $defaultCollectionTime,
            'initialEntries' => $initialEntries,
        ]);
    }

    public function entries(Request $request, Team $current_team, FetchDataEntryEntriesAction $action): JsonResponse
    {
        $request->validate([
            'collected_at' => ['required', 'string'],
        ]);

        $result = $action->execute($current_team, (string) $request->input('collected_at'));

        return response()->json($result);
    }

    public function store(StoreDataEntryRequest $request, Team $current_team, StoreDataEntryAction $action): JsonResponse
    {
        $validated = $request->validated();

        $result = $action->execute(
            team: $current_team,
            user: $request->user(),
            monitoredSystemId: (int) $validated['monitored_system_id'],
            collectedAt: (string) $validated['collected_at'],
            values: (array) $validated['values'],
            comment: isset($validated['comment']) ? (string) $validated['comment'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Dados de medição salvos com sucesso!',
            'data' => $result,
        ]);
    }

    public function storeBatch(StoreBatchDataEntryRequest $request, Team $current_team, StoreBatchDataEntryAction $action): JsonResponse
    {
        $validated = $request->validated();

        $result = $action->execute(
            team: $current_team,
            user: $request->user(),
            collectedAt: (string) $validated['collected_at'],
            systems: (array) $validated['systems'],
        );

        return response()->json([
            'success' => true,
            'message' => "Dados de {$result['total_systems_saved']} sistema(s) salvos no banco com sucesso!",
            'data' => $result,
        ]);
    }
}

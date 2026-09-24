<?php

namespace App\Features\SpreadsheetImports\Http\Controllers;

use App\Features\SpreadsheetImports\Actions\EnqueueSpreadsheetImportAction;
use App\Features\SpreadsheetImports\Actions\ExecuteSpreadsheetImportAction;
use App\Features\SpreadsheetImports\Actions\ParseSpreadsheetPreviewAction;
use App\Features\SpreadsheetImports\Actions\RevertSpreadsheetImportAction;
use App\Features\SpreadsheetImports\Http\Requests\EnqueueManualSpreadsheetImportRequest;
use App\Features\SpreadsheetImports\Http\Requests\ExecuteSpreadsheetImportRequest;
use App\Features\SpreadsheetImports\Http\Requests\PreviewSpreadsheetImportRequest;
use App\Http\Controllers\Controller;
use App\Models\Parameter;
use App\Models\SpreadsheetEmailInboxItem;
use App\Models\SpreadsheetImportBatch;
use App\Models\SpreadsheetImportQueue;
use App\Models\SpreadsheetTemplate;
use App\Models\Team;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetImportController extends Controller
{
    public function index(Request $request, Team $current_team): Response
    {
        $user = $request->user();
        $availableTeams = $user->is_admin
            ? Team::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug'])
            : $user->teams()->where('teams.is_active', true)->orderBy('name')->get(['teams.id', 'teams.name', 'teams.slug']);

        $availableTeamIds = $availableTeams->pluck('id')->all();

        $selectedFilter = $request->input('filter_team_id');
        $filteredTeamId = null;
        if ($selectedFilter && $selectedFilter !== 'all') {
            $val = (int) $selectedFilter;
            if (in_array($val, $availableTeamIds, true)) {
                $filteredTeamId = $val;
            }
        }

        $scopedTeamIds = $filteredTeamId ? [$filteredTeamId] : $availableTeamIds;

        $templates = SpreadsheetTemplate::query()
            ->whereIn('team_id', $scopedTeamIds)
            ->where('is_active', true)
            ->with(['team:id,name,slug'])
            ->orderBy('name')
            ->get(['id', 'team_id', 'name', 'description', 'config']);

        $batches = SpreadsheetImportBatch::query()
            ->whereIn('team_id', $scopedTeamIds)
            ->with(['team:id,name,slug', 'user:id,name', 'template:id,name', 'revertedBy:id,name'])
            ->latest()
            ->paginate(15, ['*'], 'batches_page')
            ->withQueryString();

        $pendingConfirmations = SpreadsheetEmailInboxItem::query()
            ->whereIn('team_id', $scopedTeamIds)
            ->where('status', 'pending_confirmation')
            ->with(['team:id,name,slug', 'template:id,name', 'rule:id,name'])
            ->latest()
            ->paginate(15, ['*'], 'pending_page')
            ->withQueryString();

        // Active queue excludes 'completed' so finished items leave the queue and stay in history
        $queueItems = SpreadsheetImportQueue::query()
            ->whereIn('team_id', $scopedTeamIds)
            ->whereIn('status', ['pending', 'processing', 'failed'])
            ->with(['team:id,name,slug', 'template:id,name', 'user:id,name', 'batch:id,saved_values_count'])
            ->latest()
            ->paginate(15, ['*'], 'queue_page')
            ->withQueryString();

        $pendingCount = SpreadsheetEmailInboxItem::query()
            ->whereIn('team_id', $scopedTeamIds)
            ->where('status', 'pending_confirmation')
            ->count();

        $queueActiveCount = SpreadsheetImportQueue::query()
            ->whereIn('team_id', $scopedTeamIds)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        return Inertia::render('SpreadsheetImports/Index', [
            'templates' => $templates,
            'batches' => $batches,
            'pendingConfirmations' => $pendingConfirmations,
            'queueItems' => $queueItems,
            'pendingCount' => $pendingCount,
            'queueActiveCount' => $queueActiveCount,
            'currentTeam' => $current_team,
            'availableTeams' => $availableTeams,
            'selectedTeamId' => $filteredTeamId ? (string) $filteredTeamId : 'all',
            'defaultReferenceDate' => Carbon::now()->format('Y-m-d'),
        ]);
    }

    public function preview(
        PreviewSpreadsheetImportRequest $request,
        Team $current_team,
        ParseSpreadsheetPreviewAction $action,
    ): JsonResponse {
        $validated = $request->validated();
        $user = $request->user();

        $availableTeamIds = $user->is_admin
            ? Team::query()->where('is_active', true)->pluck('id')->all()
            : $user->teams()->where('teams.is_active', true)->pluck('teams.id')->all();

        $template = SpreadsheetTemplate::with('team')
            ->whereIn('team_id', $availableTeamIds)
            ->findOrFail((int) $validated['spreadsheet_template_id']);

        $targetTeam = $template->team;
        $file = $request->file('file');

        $storageDisk = (string) config('spreadsheet_email.storage_disk', 'local');
        $baseFolder = date('Y/m/d')."/{$targetTeam->slug}";
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext = $file->getClientOriginalExtension();
        $storedName = date('Ymd_His').'_'.Str::random(8)."_{$safeName}.{$ext}";
        $relativePath = "{$baseFolder}/{$storedName}";

        $file->storeAs($baseFolder, $storedName, $storageDisk);
        $fullPath = Storage::disk($storageDisk)->path($relativePath);

        try {
            $preview = $action->execute(
                team: $targetTeam,
                template: $template,
                filePath: $fullPath,
                referenceDate: $validated['reference_date'] ?? null,
                dateScope: $validated['date_scope'] ?? null,
            );

            return response()->json([
                'success' => true,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $relativePath,
                'preview' => $preview,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar o arquivo: '.$e->getMessage(),
            ], 422);
        }
    }

    public function enqueueManual(
        EnqueueManualSpreadsheetImportRequest $request,
        Team $current_team,
        EnqueueSpreadsheetImportAction $action,
    ): JsonResponse {
        $validated = $request->validated();
        $user = $request->user();

        $availableTeamIds = $user->is_admin
            ? Team::query()->where('is_active', true)->pluck('id')->all()
            : $user->teams()->where('teams.is_active', true)->pluck('teams.id')->all();

        $template = SpreadsheetTemplate::with('team')
            ->whereIn('team_id', $availableTeamIds)
            ->findOrFail((int) $validated['spreadsheet_template_id']);

        $targetTeam = $template->team;

        try {
            $queueItem = $action->enqueueFromManualUpload(
                team: $targetTeam,
                user: $user,
                template: $template,
                file: $request->file('file'),
                referenceDate: $validated['reference_date'] ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => 'Arquivo enviado para a fila de importação com sucesso!',
                'queue_id' => $queueItem->id,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enfileirar arquivo: '.$e->getMessage(),
            ], 500);
        }
    }

    public function execute(
        ExecuteSpreadsheetImportRequest $request,
        Team $current_team,
        ExecuteSpreadsheetImportAction $action,
    ): JsonResponse {
        $validated = $request->validated();
        $user = $request->user();

        $availableTeamIds = $user->is_admin
            ? Team::query()->where('is_active', true)->pluck('id')->all()
            : $user->teams()->where('teams.is_active', true)->pluck('teams.id')->all();

        $template = SpreadsheetTemplate::with('team')
            ->whereIn('team_id', $availableTeamIds)
            ->findOrFail((int) $validated['spreadsheet_template_id']);

        $targetTeam = $template->team;

        try {
            $result = $action->execute(
                team: $targetTeam,
                user: $user,
                template: $template,
                fileName: (string) $validated['file_name'],
                filePath: $validated['file_path'] ?? null,
                referenceDate: $validated['reference_date'] ?? null,
                items: (array) $validated['items'],
            );

            return response()->json([
                'success' => true,
                'message' => "Importação concluída com sucesso! {$result['saved_count']} medições processadas.",
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar os dados: '.$e->getMessage(),
            ], 500);
        }
    }

    public function batchItems(
        Request $request,
        Team $current_team,
        SpreadsheetImportBatch $batch,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user->is_admin || $user->belongsToTeam($batch->team), 403);

        $snapshot = $batch->snapshot ?? [];
        $items = $snapshot['items'] ?? [];
        $summary = $snapshot['summary'] ?? null;

        if (empty($items) && ! empty($snapshot)) {
            $changes = isset($snapshot['changes']) && is_array($snapshot['changes']) ? $snapshot['changes'] : $snapshot;
            $items = $this->reconstructItemsFromLegacySnapshot($changes);
        }

        $totalExtracted = $summary['total_extracted'] ?? count($items);
        $totalNew = $summary['total_new'] ?? count(array_filter($items, fn ($i) => empty($i['is_update']) && ($i['status'] ?? '') === 'valid'));
        $totalUpdates = $summary['total_updates'] ?? count(array_filter($items, fn ($i) => ! empty($i['is_update'])));
        $totalInvalid = $summary['total_empty_or_invalid'] ?? count(array_filter($items, fn ($i) => ($i['status'] ?? '') === 'empty_or_invalid'));

        return response()->json([
            'success' => true,
            'file_name' => $batch->file_name,
            'file_path' => $batch->file_path,
            'preview' => [
                'template_name' => $batch->template?->name ?? 'Modelo importado',
                'total_extracted' => $totalExtracted,
                'total_new' => $totalNew,
                'total_updates' => $totalUpdates,
                'total_empty_or_invalid' => $totalInvalid,
                'warnings' => [],
                'items' => $items,
            ],
        ]);
    }

    public function download(
        Request $request,
        Team $current_team,
        SpreadsheetImportBatch $batch,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user->is_admin || $user->belongsToTeam($batch->team), 403);

        $storageDisk = (string) config('spreadsheet_email.storage_disk', 'local');

        if (! $batch->file_path || ! Storage::disk($storageDisk)->exists($batch->file_path)) {
            abort(404, 'Arquivo da planilha não encontrado no armazenamento do servidor.');
        }

        return Storage::disk($storageDisk)->download($batch->file_path, $batch->file_name);
    }

    public function revert(
        Request $request,
        Team $current_team,
        SpreadsheetImportBatch $batch,
        RevertSpreadsheetImportAction $action,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user->is_admin || $user->belongsToTeam($batch->team), 403);

        try {
            $action->execute($batch, $user);

            return response()->json([
                'success' => true,
                'message' => 'Lote de importação revertido com sucesso!',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reconstruct items list for legacy batches without full items snapshot.
     *
     * @param  array<int, array<string, mixed>>  $changes
     * @return array<int, array<string, mixed>>
     */
    private function reconstructItemsFromLegacySnapshot(array $changes): array
    {
        $paramIds = array_unique(array_filter(array_column($changes, 'parameter_id')));
        $parameters = Parameter::with('monitoredSystem')->whereIn('id', $paramIds)->get()->keyBy('id');

        $items = [];
        foreach ($changes as $c) {
            $paramId = (int) ($c['parameter_id'] ?? 0);
            $param = $parameters->get($paramId);

            $items[] = [
                'system_id' => $param?->monitored_system_id ?? 0,
                'parameter_id' => $paramId,
                'system_name' => $param?->monitoredSystem?->name ?? 'Sistema',
                'parameter_name' => $param?->name ?? "Parâmetro #{$paramId}",
                'unit' => $param?->unit ?? '',
                'sheet_name' => '-',
                'cell' => '-',
                'raw_value' => $c['new_value'] ?? null,
                'multiplier' => 1,
                'final_value' => $c['new_value'] ?? null,
                'measured_at' => (string) ($c['measured_at'] ?? ''),
                'measured_date' => substr((string) ($c['measured_at'] ?? ''), 0, 10),
                'status' => 'valid',
                'is_update' => ($c['action'] ?? '') === 'update',
                'existing_value' => $c['previous_value'] ?? null,
            ];
        }

        return $items;
    }
}

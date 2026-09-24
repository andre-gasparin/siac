<?php

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\SpreadsheetImportBatch;
use App\Models\SpreadsheetImportQueue;
use App\Models\SpreadsheetTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->team1 = Team::factory()->create(['name' => 'Empresa Alfa', 'slug' => 'empresa-alfa']);
    $this->team2 = Team::factory()->create(['name' => 'Empresa Beta', 'slug' => 'empresa-beta']);

    $this->team1->members()->attach($this->user, ['role' => 'member']);
    $this->team2->members()->attach($this->user, ['role' => 'member']);
    $this->user->update(['current_team_id' => $this->team1->id]);

    $this->system1 = MonitoredSystem::create([
        'team_id' => $this->team1->id,
        'name' => 'Sistema 1',
        'is_active' => true,
    ]);

    $this->param1 = Parameter::create([
        'team_id' => $this->team1->id,
        'monitored_system_id' => $this->system1->id,
        'name' => 'pH Alfa',
        'unit' => 'pH',
        'decimals' => 2,
        'is_active' => true,
    ]);

    $this->template1 = SpreadsheetTemplate::create([
        'team_id' => $this->team1->id,
        'name' => 'Template Alfa',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_name' => 'Sheet1',
                    'date_mode' => 'cell_reference',
                    'date_cell' => 'A1',
                    'mappings' => [
                        [
                            'cell' => 'B2',
                            'monitored_system_id' => $this->system1->id,
                            'parameter_id' => $this->param1->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $this->template2 = SpreadsheetTemplate::create([
        'team_id' => $this->team2->id,
        'name' => 'Template Beta',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_name' => 'Sheet1',
                    'date_mode' => 'cell_reference',
                    'date_cell' => 'A1',
                    'mappings' => [],
                ],
            ],
        ],
        'is_active' => true,
    ]);
});

function createSampleExcel(): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Sheet1');
    $sheet->setCellValue('A1', '2026-09-16');
    $sheet->setCellValue('B2', '7.45');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_enh_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);

    return new UploadedFile($tempFile, 'teste_empresa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('stores files in Y/m/d/empresa directory structure when generating preview', function () {
    $file = createSampleExcel();

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team1->slug]), [
            'spreadsheet_template_id' => $this->template1->id,
            'reference_date' => '2026-09-16',
            'file' => $file,
        ]);

    $response->assertOk();
    $filePath = $response->json('file_path');

    expect($filePath)->not->toBeNull();
    expect($filePath)->toStartWith(date('Y/m/d')."/{$this->team1->slug}/");
    expect(Storage::disk('local')->exists($filePath))->toBeTrue();
});

test('stores files in Y/m/d/empresa directory structure when enqueuing manual upload', function () {
    $file = createSampleExcel();

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.enqueue-manual', ['current_team' => $this->team1->slug]), [
            'spreadsheet_template_id' => $this->template1->id,
            'reference_date' => '2026-09-16',
            'file' => $file,
        ]);

    $response->assertOk();
    $queueItem = SpreadsheetImportQueue::query()->where('team_id', $this->team1->id)->latest('id')->first();

    expect($queueItem)->not->toBeNull();
    expect($queueItem->file_path)->toStartWith(date('Y/m/d')."/{$this->team1->slug}/");
    expect(Storage::disk('local')->exists($queueItem->file_path))->toBeTrue();
});

test('can download batch excel file if it exists, or returns 404 if missing', function () {
    $relativeStoragePath = date('Y/m/d')."/{$this->team1->slug}/planilha_gravada.xlsx";
    Storage::disk('local')->put($relativeStoragePath, 'conteudo_excel_binario');

    $batch = SpreadsheetImportBatch::create([
        'team_id' => $this->team1->id,
        'user_id' => $this->user->id,
        'spreadsheet_template_id' => $this->template1->id,
        'file_name' => 'planilha_gravada.xlsx',
        'file_path' => $relativeStoragePath,
        'reference_date' => '2026-09-16',
        'status' => 'completed',
        'saved_values_count' => 10,
    ]);

    // Download existing file
    $response = $this->actingAs($this->user)
        ->get(route('spreadsheet-imports.batches.download', [
            'current_team' => $this->team1->slug,
            'batch' => $batch->id,
        ]));

    $response->assertOk();

    // 404 for missing file
    $batchMissing = SpreadsheetImportBatch::create([
        'team_id' => $this->team1->id,
        'user_id' => $this->user->id,
        'spreadsheet_template_id' => $this->template1->id,
        'file_name' => 'arquivo_inexistente.xlsx',
        'file_path' => 'caminho/nao/existe.xlsx',
        'status' => 'completed',
        'saved_values_count' => 0,
    ]);

    $missingResponse = $this->actingAs($this->user)
        ->get(route('spreadsheet-imports.batches.download', [
            'current_team' => $this->team1->slug,
            'batch' => $batchMissing->id,
        ]));

    $missingResponse->assertNotFound();
});

test('batchItems endpoint returns preview structure with items and summary', function () {
    $items = [
        [
            'sheet_name' => 'Sheet1',
            'cell' => 'B2',
            'system_id' => $this->system1->id,
            'system_name' => $this->system1->name,
            'parameter_id' => $this->param1->id,
            'parameter_name' => $this->param1->name,
            'unit' => 'pH',
            'raw_value' => '7.45',
            'multiplier' => 1,
            'final_value' => 7.45,
            'measured_at' => '2026-09-16 10:00:00',
            'measured_date' => '2026-09-16',
            'status' => 'valid',
            'is_update' => false,
            'existing_value' => null,
        ],
    ];

    $batch = SpreadsheetImportBatch::create([
        'team_id' => $this->team1->id,
        'user_id' => $this->user->id,
        'spreadsheet_template_id' => $this->template1->id,
        'file_name' => 'teste_detalhes.xlsx',
        'file_path' => 'path/teste.xlsx',
        'reference_date' => '2026-09-16',
        'status' => 'completed',
        'saved_values_count' => 1,
        'snapshot' => [
            'changes' => [],
            'items' => $items,
            'summary' => [
                'total_extracted' => 1,
                'total_new' => 1,
                'total_updates' => 0,
                'total_empty_or_invalid' => 0,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('spreadsheet-imports.batches.items', [
            'current_team' => $this->team1->slug,
            'batch' => $batch->id,
        ]));

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'file_name' => 'teste_detalhes.xlsx',
        'preview' => [
            'total_extracted' => 1,
            'total_new' => 1,
            'total_updates' => 0,
        ],
    ]);

    expect($response->json('preview.items'))->toHaveCount(1);
    expect($response->json('preview.items.0.cell'))->toBe('B2');
    expect($response->json('preview.items.0.final_value'))->toBe(7.45);
});

test('completed items are excluded from queue tab in index and multi-team filter works', function () {
    // 1 pending queue item in team 1
    $pendingQueue = SpreadsheetImportQueue::create([
        'team_id' => $this->team1->id,
        'user_id' => $this->user->id,
        'spreadsheet_template_id' => $this->template1->id,
        'source_type' => 'manual_upload',
        'file_name' => 'fila_pendente.xlsx',
        'file_path' => 'path/pendente.xlsx',
        'status' => 'pending',
    ]);

    // 1 completed queue item in team 1 (should NOT appear in index queue)
    SpreadsheetImportQueue::create([
        'team_id' => $this->team1->id,
        'user_id' => $this->user->id,
        'spreadsheet_template_id' => $this->template1->id,
        'source_type' => 'manual_upload',
        'file_name' => 'fila_concluida.xlsx',
        'file_path' => 'path/concluida.xlsx',
        'status' => 'completed',
    ]);

    // 1 pending queue item in team 2
    $pendingQueue2 = SpreadsheetImportQueue::create([
        'team_id' => $this->team2->id,
        'user_id' => $this->user->id,
        'spreadsheet_template_id' => $this->template2->id,
        'source_type' => 'manual_upload',
        'file_name' => 'fila_pendente_beta.xlsx',
        'file_path' => 'path/beta.xlsx',
        'status' => 'pending',
    ]);

    // Visit index with no filter (general / all teams)
    $responseAll = $this->actingAs($this->user)
        ->get(route('spreadsheet-imports.index', ['current_team' => $this->team1->slug]));

    $responseAll->assertOk();
    $responseAll->assertInertia(fn ($page) => $page
        ->component('SpreadsheetImports/Index')
        ->has('templates', 2) // Both template1 and template2
        ->has('queueItems.data', 2) // pendingQueue and pendingQueue2 (completed is excluded!)
        ->where('selectedTeamId', 'all')
    );

    // Visit index filtered by team 1 only
    $responseFiltered = $this->actingAs($this->user)
        ->get(route('spreadsheet-imports.index', [
            'current_team' => $this->team1->slug,
            'filter_team_id' => $this->team1->id,
        ]));

    $responseFiltered->assertOk();
    $responseFiltered->assertInertia(fn ($page) => $page
        ->component('SpreadsheetImports/Index')
        ->has('templates', 1) // Only template1
        ->has('queueItems.data', 1) // Only team1's pending item
        ->where('selectedTeamId', (string) $this->team1->id)
    );
});

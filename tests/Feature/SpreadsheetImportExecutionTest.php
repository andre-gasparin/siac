<?php

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\SpreadsheetTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => 'member']);
    $this->user->update(['current_team_id' => $this->team->id]);

    $this->system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Caldeira 1',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->paramPh = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'pH da Caldeira',
        'unit' => 'pH',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->paramCond = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'Condutividade',
        'unit' => 'µS/cm',
        'decimals' => 1,
        'sort_order' => 2,
        'is_active' => true,
    ]);
});

function createTestExcelFile(): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Caldeira');

    // Headers and values
    $sheet->setCellValue('B6', '25/08/2026'); // Date cell
    $sheet->setCellValue('C7', '14:30');      // Time cell
    $sheet->setCellValue('D7', '9.45');       // pH value
    $sheet->setCellValue('E7', '12.5');       // Cond value (will use multiplier 100 -> 1250)

    $tempFile = tempnam(sys_get_temp_dir(), 'test_sheet_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);

    return new UploadedFile($tempFile, 'test_caldeira.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('can preview spreadsheet import with dates, coordinates and multipliers', function () {
    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Teste Caldeira',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_identifier_type' => 'index',
                    'sheet_identifier_value' => 1,
                    'sheet_name' => 'Caldeira',
                    'date_mode' => 'cell_reference',
                    'date_cell' => 'B6',
                    'time_cell' => 'C7',
                    'mappings' => [
                        [
                            'cell' => 'D7',
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramPh->id,
                            'multiplier' => 1.0,
                        ],
                        [
                            'cell' => 'E7',
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramCond->id,
                            'multiplier' => 100.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $file = createTestExcelFile();

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'file' => $file,
        ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'preview' => [
            'total_extracted' => 2,
            'total_new' => 2,
            'total_updates' => 0,
        ],
    ]);

    $items = $response->json('preview.items');
    expect($items)->toHaveCount(2);

    // Verify pH
    expect($items[0]['parameter_id'])->toBe($this->paramPh->id);
    expect($items[0]['final_value'])->toEqual(9.45);
    expect($items[0]['measured_at'])->toBe('2026-08-25 14:30:00');

    // Verify Cond with multiplier 100
    expect($items[1]['parameter_id'])->toBe($this->paramCond->id);
    expect($items[1]['final_value'])->toEqual(1250);
});

test('can execute spreadsheet import and persist values to parameter_values', function () {
    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Teste',
        'config' => ['version' => 1, 'sheets' => []],
        'is_active' => true,
    ]);

    $items = [
        [
            'system_id' => $this->system->id,
            'parameter_id' => $this->paramPh->id,
            'final_value' => 9.45,
            'measured_at' => '2026-08-25 14:30:00',
            'measured_date' => '2026-08-25',
            'status' => 'valid',
        ],
        [
            'system_id' => $this->system->id,
            'parameter_id' => $this->paramCond->id,
            'final_value' => 1250,
            'measured_at' => '2026-08-25 14:30:00',
            'measured_date' => '2026-08-25',
            'status' => 'valid',
        ],
    ];

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.execute', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'file_name' => 'test_caldeira.xlsx',
            'reference_date' => '2026-08-25',
            'items' => $items,
        ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'data' => [
            'saved_count' => 2,
            'created_count' => 2,
            'updated_count' => 0,
        ],
    ]);

    $this->assertDatabaseHas('parameter_values', [
        'team_id' => $this->team->id,
        'parameter_id' => $this->paramPh->id,
        'value' => 9.45,
        'source_type' => 'spreadsheet',
    ]);

    $this->assertDatabaseHas('parameter_values', [
        'team_id' => $this->team->id,
        'parameter_id' => $this->paramCond->id,
        'value' => 1250,
        'source_type' => 'spreadsheet',
    ]);

    $this->assertDatabaseHas('spreadsheet_import_batches', [
        'team_id' => $this->team->id,
        'file_name' => 'test_caldeira.xlsx',
        'saved_values_count' => 2,
        'status' => 'completed',
    ]);
});

test('can revert a spreadsheet import batch', function () {
    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Teste',
        'config' => ['version' => 1, 'sheets' => []],
        'is_active' => true,
    ]);

    // Create an existing reading
    $existingValue = ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'parameter_id' => $this->paramPh->id,
        'measured_at' => '2026-08-25 14:30:00',
        'measured_date' => '2026-08-25',
        'value' => 7.00,
        'source_type' => 'manual',
    ]);

    // Import updates pH and creates new Cond reading
    $items = [
        [
            'system_id' => $this->system->id,
            'parameter_id' => $this->paramPh->id,
            'final_value' => 9.45,
            'measured_at' => '2026-08-25 14:30:00',
            'measured_date' => '2026-08-25',
            'status' => 'valid',
        ],
        [
            'system_id' => $this->system->id,
            'parameter_id' => $this->paramCond->id,
            'final_value' => 1250,
            'measured_at' => '2026-08-25 14:30:00',
            'measured_date' => '2026-08-25',
            'status' => 'valid',
        ],
    ];

    $executeResp = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.execute', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'file_name' => 'test_caldeira.xlsx',
            'reference_date' => '2026-08-25',
            'items' => $items,
        ]);

    $executeResp->assertOk();
    $batchId = $executeResp->json('data.batch_id');

    // Confirm updated
    expect($existingValue->fresh()->value)->toEqual(9.45);

    // Now revert the batch
    $revertResp = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.revert', [
            'current_team' => $this->team->slug,
            'batch' => $batchId,
        ]));

    $revertResp->assertOk();

    // Confirm previous value is restored
    expect($existingValue->fresh()->value)->toEqual(7.00);

    // Confirm created cond value was deleted
    $condValue = ParameterValue::query()
        ->where('parameter_id', $this->paramCond->id)
        ->where('measured_at', '2026-08-25 14:30:00')
        ->first();
    expect($condValue)->toBeNull();

    $this->assertDatabaseHas('spreadsheet_import_batches', [
        'id' => $batchId,
        'status' => 'reverted',
        'reverted_by' => $this->user->id,
    ]);
});

function createHorizontalTestExcelFile(): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('PlanilhaHorizontal');

    // Row 2: Dates
    $sheet->setCellValue('B2', '08/09/2026');
    $sheet->setCellValue('C2', '09/09/2026');
    $sheet->setCellValue('D2', '10/09/2026');

    // Row 3: Parameter pH
    $sheet->setCellValue('A3', 'pH');
    $sheet->setCellValue('B3', '7.20');
    $sheet->setCellValue('C3', '7.50');
    $sheet->setCellValue('D3', '7.80');

    // Row 4: Parameter Cond
    $sheet->setCellValue('A4', 'Condutividade');
    $sheet->setCellValue('B4', '100');
    $sheet->setCellValue('C4', '120');
    $sheet->setCellValue('D4', '150');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_horiz_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);

    return new UploadedFile($tempFile, 'test_horizontal.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('can preview horizontal series import for a single target date', function () {
    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Horizontal',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_identifier_type' => 'name',
                    'sheet_identifier_value' => 'PlanilhaHorizontal',
                    'sheet_name' => 'PlanilhaHorizontal',
                    'date_mode' => 'horizontal_series',
                    'date_row' => 2,
                    'mappings' => [
                        [
                            'cell' => 'B3',
                            'row_or_col' => 3,
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramPh->id,
                            'multiplier' => 1.0,
                        ],
                        [
                            'cell' => 'B4',
                            'row_or_col' => 4,
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramCond->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $file = createHorizontalTestExcelFile();

    // Request preview for date 09/09/2026
    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'reference_date' => '2026-09-09',
            'date_scope' => 'single',
            'file' => $file,
        ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'preview' => [
            'total_extracted' => 2,
            'total_new' => 2,
        ],
    ]);

    $items = $response->json('preview.items');
    expect($items)->toHaveCount(2);

    // Should have picked column C (09/09/2026) -> pH = 7.50, Cond = 120
    expect($items[0]['parameter_id'])->toBe($this->paramPh->id);
    expect($items[0]['final_value'])->toEqual(7.50);
    expect($items[0]['measured_at'])->toBe('2026-09-09 00:00:00');

    expect($items[1]['parameter_id'])->toBe($this->paramCond->id);
    expect($items[1]['final_value'])->toEqual(120);
    expect($items[1]['measured_at'])->toBe('2026-09-09 00:00:00');
});

test('can preview horizontal series import scanning all dates', function () {
    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Horizontal Todas as Datas',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_identifier_type' => 'name',
                    'sheet_identifier_value' => 'PlanilhaHorizontal',
                    'sheet_name' => 'PlanilhaHorizontal',
                    'date_mode' => 'horizontal_series',
                    'date_row' => 2,
                    'mappings' => [
                        [
                            'cell' => 'B3',
                            'row_or_col' => 3,
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramPh->id,
                            'multiplier' => 1.0,
                        ],
                        [
                            'cell' => 'B4',
                            'row_or_col' => 4,
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramCond->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $file = createHorizontalTestExcelFile();

    // Request preview with date_scope = 'all'
    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'date_scope' => 'all',
            'file' => $file,
        ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'preview' => [
            'total_extracted' => 6, // 3 dates * 2 params
        ],
    ]);

    $items = $response->json('preview.items');
    expect($items)->toHaveCount(6);

    // Verify all 3 dates are present
    $dates = array_unique(array_map(fn ($i) => substr($i['measured_at'], 0, 10), $items));
    sort($dates);
    expect($dates)->toBe(['2026-09-08', '2026-09-09', '2026-09-10']);
});

test('sample preview automatically detects horizontal series orientation and date row', function () {
    $file = createHorizontalTestExcelFile();

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.templates.sample-preview', ['current_team' => $this->team->slug]), [
            'file' => $file,
        ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
    ]);

    $sheet = $response->json('sheets.0');
    expect($sheet['suggested_orientation'])->toBe('horizontal_series');
    expect($sheet['suggested_date_row'])->toBe(2);
});

test('can preview horizontal series with separate time row', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('HorizComHoras');

    // Row 2: Dates
    $sheet->setCellValue('B2', '08/09/2026');
    $sheet->setCellValue('C2', '09/09/2026');

    // Row 3: Times
    $sheet->setCellValue('B3', '08:30');
    $sheet->setCellValue('C3', '14:45');

    // Row 4: Parameter pH
    $sheet->setCellValue('A4', 'pH');
    $sheet->setCellValue('B4', '7.20');
    $sheet->setCellValue('C4', '7.50');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_time_row_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);
    $file = new UploadedFile($tempFile, 'test_time_row.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo com Horas',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_identifier_type' => 'name',
                    'sheet_identifier_value' => 'HorizComHoras',
                    'sheet_name' => 'HorizComHoras',
                    'date_mode' => 'horizontal_series',
                    'date_row' => 2,
                    'time_row' => 3,
                    'mappings' => [
                        [
                            'cell' => 'B4',
                            'row_or_col' => 4,
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramPh->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'date_scope' => 'all',
            'file' => $file,
        ]);

    $response->assertOk();
    $items = $response->json('preview.items');
    expect($items)->toHaveCount(2);

    expect($items[0]['measured_at'])->toBe('2026-09-08 08:30:00');
    expect($items[1]['measured_at'])->toBe('2026-09-09 14:45:00');
});

test('can preview horizontal series with time embedded in the date cell', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('HorizDataHoraJuntas');

    // Row 2: Date + Time together
    $sheet->setCellValue('B2', '08/09/2026 09:15');
    $sheet->setCellValue('C2', '09/09/2026 15:30');

    // Row 3: Parameter pH
    $sheet->setCellValue('A3', 'pH');
    $sheet->setCellValue('B3', '7.20');
    $sheet->setCellValue('C3', '7.50');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_datetime_cell_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);
    $file = new UploadedFile($tempFile, 'test_datetime_cell.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Data e Hora Juntas',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_identifier_type' => 'name',
                    'sheet_identifier_value' => 'HorizDataHoraJuntas',
                    'sheet_name' => 'HorizDataHoraJuntas',
                    'date_mode' => 'horizontal_series',
                    'date_row' => 2,
                    'mappings' => [
                        [
                            'cell' => 'B3',
                            'row_or_col' => 3,
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramPh->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'date_scope' => 'all',
            'file' => $file,
        ]);

    $response->assertOk();
    $items = $response->json('preview.items');
    expect($items)->toHaveCount(2);

    expect($items[0]['measured_at'])->toBe('2026-09-08 09:15:00');
    expect($items[1]['measured_at'])->toBe('2026-09-09 15:30:00');
});

test('can preview vertical series with separate time column', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('VertComHoras');

    // Row 1: Headers
    $sheet->setCellValue('A1', 'Data');
    $sheet->setCellValue('B1', 'Hora');
    $sheet->setCellValue('C1', 'pH');

    // Row 2
    $sheet->setCellValue('A2', '08/09/2026');
    $sheet->setCellValue('B2', '11:30');
    $sheet->setCellValue('C2', '7.20');

    // Row 3
    $sheet->setCellValue('A3', '09/09/2026');
    $sheet->setCellValue('B3', '16:45');
    $sheet->setCellValue('C3', '7.50');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_vert_time_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);
    $file = new UploadedFile($tempFile, 'test_vert_time.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Vertical com Horas',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_identifier_type' => 'name',
                    'sheet_identifier_value' => 'VertComHoras',
                    'sheet_name' => 'VertComHoras',
                    'date_mode' => 'vertical_series',
                    'date_column' => 'A',
                    'time_column' => 'B',
                    'mappings' => [
                        [
                            'cell' => 'C2',
                            'row_or_col' => 'C',
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramPh->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'date_scope' => 'all',
            'file' => $file,
        ]);

    $response->assertOk();
    $items = $response->json('preview.items');
    expect($items)->toHaveCount(2);

    expect($items[0]['measured_at'])->toBe('2026-09-08 11:30:00');
    expect($items[1]['measured_at'])->toBe('2026-09-09 16:45:00');
});

test('filters measurements by allowed_times in horizontal series with multiple hours on same date', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Turnos');

    // Row 2: Dates with time (or dates and time row)
    $sheet->setCellValue('B2', '10/09/2026');
    $sheet->setCellValue('B3', '07:00');
    $sheet->setCellValue('B4', '7.10');

    $sheet->setCellValue('C2', '10/09/2026');
    $sheet->setCellValue('C3', '12:00'); // Should be ignored by filter!
    $sheet->setCellValue('C4', '7.20');

    $sheet->setCellValue('D2', '10/09/2026');
    $sheet->setCellValue('D3', '15:00');
    $sheet->setCellValue('D4', '7.30');

    $sheet->setCellValue('E2', '10/09/2026');
    $sheet->setCellValue('E3', '23:00');
    $sheet->setCellValue('E4', '7.40');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_allowed_times_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);
    $file = new UploadedFile($tempFile, 'test_allowed_times.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Filtro Turnos',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_identifier_type' => 'name',
                    'sheet_identifier_value' => 'Turnos',
                    'sheet_name' => 'Turnos',
                    'date_mode' => 'horizontal_series',
                    'date_row' => 2,
                    'time_row' => 3,
                    'allowed_times' => ['07:00', '15:00', '23:00'],
                    'mappings' => [
                        [
                            'cell' => 'B4',
                            'row_or_col' => 4,
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramPh->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    // Single date scope: should return all 3 allowed shifts for that date
    $responseSingle = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'reference_date' => '2026-09-10',
            'date_scope' => 'single',
            'file' => $file,
        ]);

    $responseSingle->assertOk();
    $itemsSingle = $responseSingle->json('preview.items');
    expect($itemsSingle)->toHaveCount(3);
    expect($itemsSingle[0]['measured_at'])->toBe('2026-09-10 07:00:00');
    expect($itemsSingle[0]['final_value'])->toBe(7.1);
    expect($itemsSingle[1]['measured_at'])->toBe('2026-09-10 15:00:00');
    expect($itemsSingle[1]['final_value'])->toBe(7.3);
    expect($itemsSingle[2]['measured_at'])->toBe('2026-09-10 23:00:00');
    expect($itemsSingle[2]['final_value'])->toBe(7.4);
});

test('horizontal series parses columns beyond Z and accurately matches allowed_times with float precision', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Caldeira');

    // Row 2: dates spanning columns A to AB (col 28)
    $sheet->setCellValue('AA2', '10/09/2026');
    $sheet->setCellValue('AB2', '10/09/2026');

    // Row 3: times with float imprecision
    // 7/24 with IEEE 754 precision error (0.29166666666666663)
    $sheet->setCellValue('AA3', 0.29166666666666663);
    $sheet->setCellValue('AB3', 15 / 24);

    // Row 4: values
    $sheet->setCellValue('AA4', 8.50);
    $sheet->setCellValue('AB4', 8.60);

    $tempFile = tempnam(sys_get_temp_dir(), 'test_col_beyond_z_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);

    $file = new UploadedFile($tempFile, 'test_col_beyond_z.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Além de Z',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_name' => 'Caldeira',
                    'date_mode' => 'horizontal_series',
                    'date_row' => 2,
                    'time_row' => 3,
                    'allowed_times' => ['07:00', '15:00'],
                    'mappings' => [
                        [
                            'cell' => 'AA4',
                            'row_or_col' => 4,
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramPh->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'reference_date' => '2026-09-10',
            'date_scope' => 'all',
            'file' => $file,
        ]);

    $response->assertOk();
    $items = $response->json('preview.items');
    expect($items)->toHaveCount(2);

    // First item in col AA must have matched 07:00:00 (not 06:59:59)
    expect($items[0]['cell'])->toBe('AA4');
    expect($items[0]['measured_at'])->toBe('2026-09-10 07:00:00');
    expect($items[0]['final_value'])->toBe(8.5);

    // Second item in col AB must have matched 15:00:00
    expect($items[1]['cell'])->toBe('AB4');
    expect($items[1]['measured_at'])->toBe('2026-09-10 15:00:00');
    expect($items[1]['final_value'])->toBe(8.6);
});

function createTestExcelWithEmptyAndInvalid(): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Caldeira');

    $sheet->setCellValue('B6', '25/08/2026');
    $sheet->setCellValue('C7', '14:30');
    $sheet->setCellValue('D7', '');         // empty
    $sheet->setCellValue('E7', 'INVALIDO'); // text error
    $sheet->setCellValue('F7', '10.5');     // valid number

    $tempFile = tempnam(sys_get_temp_dir(), 'test_empty_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);

    return new UploadedFile($tempFile, 'test_empty_cells.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('ignores empty cells when empty_values_mode is ignore or by default', function () {
    $file = createTestExcelWithEmptyAndInvalid();

    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Ignora Vazios',
        'config' => [
            'version' => 1,
            'empty_values_mode' => 'ignore',
            'sheets' => [
                [
                    'sheet_name' => 'Caldeira',
                    'date_mode' => 'cell_reference',
                    'date_cell' => 'B6',
                    'time_cell' => 'C7',
                    'mappings' => [
                        [
                            'cell' => 'D7',
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramPh->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'file' => $file,
        ]);

    $response->assertOk();
    $items = $response->json('preview.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['status'])->toBe('empty_or_invalid');
    expect($items[0]['final_value'])->toBeNull();
    expect($items[0]['error_message'])->toBe('Célula vazia');
});

test('imports empty cells as null when empty_values_mode is save_empty', function () {
    $file = createTestExcelWithEmptyAndInvalid();

    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Salva Vazios',
        'config' => [
            'version' => 1,
            'empty_values_mode' => 'save_empty',
            'sheets' => [
                [
                    'sheet_name' => 'Caldeira',
                    'date_mode' => 'cell_reference',
                    'date_cell' => 'B6',
                    'time_cell' => 'C7',
                    'mappings' => [
                        [
                            'cell' => 'D7',
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramPh->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'file' => $file,
        ]);

    $response->assertOk();
    $items = $response->json('preview.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['status'])->toBe('valid');
    expect($items[0]['final_value'])->toBeNull();

    // Now execute the import
    $execResponse = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.execute', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'file_name' => 'test_empty_cells.xlsx',
            'items' => $items,
        ]);

    $execResponse->assertOk();
    expect($execResponse->json('data.saved_count'))->toBe(1);

    $this->assertDatabaseHas('parameter_values', [
        'team_id' => $this->team->id,
        'parameter_id' => $this->paramPh->id,
        'measured_at' => '2026-08-25 14:30:00',
        'value' => null,
    ]);
});

test('updates existing measurement to null when cell is empty and save_empty is configured', function () {
    $file = createTestExcelWithEmptyAndInvalid();

    // Pre-create existing value
    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'parameter_id' => $this->paramPh->id,
        'measured_at' => '2026-08-25 14:30:00',
        'measured_date' => '2026-08-25',
        'value' => 8.4,
        'source_type' => 'manual',
    ]);

    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Atualiza para Nulo',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_name' => 'Caldeira',
                    'date_mode' => 'cell_reference',
                    'empty_values_mode' => 'save_empty',
                    'date_cell' => 'B6',
                    'time_cell' => 'C7',
                    'mappings' => [
                        [
                            'cell' => 'D7',
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramPh->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'file' => $file,
        ]);

    $response->assertOk();
    $items = $response->json('preview.items');
    expect($items[0]['status'])->toBe('valid');
    expect($items[0]['is_update'])->toBeTrue();
    expect($items[0]['existing_value'])->toBe(8.4);
    expect($items[0]['final_value'])->toBeNull();

    $execResponse = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.execute', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'file_name' => 'test_empty_cells.xlsx',
            'items' => $items,
        ]);

    $execResponse->assertOk();
    expect($execResponse->json('data.updated_count'))->toBe(1);

    $this->assertDatabaseHas('parameter_values', [
        'team_id' => $this->team->id,
        'parameter_id' => $this->paramPh->id,
        'measured_at' => '2026-08-25 14:30:00',
        'value' => null,
    ]);
});

test('still flags non-empty invalid text as error even when save_empty is configured', function () {
    $file = createTestExcelWithEmptyAndInvalid();

    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Modelo Texto Invalido',
        'config' => [
            'version' => 1,
            'empty_values_mode' => 'save_empty',
            'sheets' => [
                [
                    'sheet_name' => 'Caldeira',
                    'date_mode' => 'cell_reference',
                    'date_cell' => 'B6',
                    'time_cell' => 'C7',
                    'mappings' => [
                        [
                            'cell' => 'E7', // Has 'INVALIDO'
                            'monitored_system_id' => $this->system->id,
                            'parameter_id' => $this->paramCond->id,
                            'multiplier' => 1.0,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.preview', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $template->id,
            'file' => $file,
        ]);

    $response->assertOk();
    $items = $response->json('preview.items');
    expect($items[0]['status'])->toBe('empty_or_invalid');
    expect($items[0]['error_message'])->toBe('Conteúdo não numérico');
});

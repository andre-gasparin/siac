<?php

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
});

test('can render templates index page', function () {
    SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Boletim Caldeira',
        'config' => [
            'version' => 1,
            'sheets' => [],
        ],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('spreadsheet-imports.templates.index', ['current_team' => $this->team->slug]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('SpreadsheetImports/Templates/Index')
        ->has('templates', 1)
    );
});

test('can render create template page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('spreadsheet-imports.templates.create', ['current_team' => $this->team->slug]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('SpreadsheetImports/Templates/Editor')
        ->has('systems')
    );
});

test('can store a new spreadsheet template', function () {
    $payload = [
        'name' => 'Boletim Turno Água',
        'description' => 'Mapeamento de parâmetros de água',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_identifier_type' => 'index',
                    'sheet_identifier_value' => 1,
                    'sheet_name' => 'Aba 1',
                    'date_mode' => 'cell_reference',
                    'date_cell' => 'B6',
                    'time_cell' => 'C7',
                    'mappings' => [
                        [
                            'cell' => 'D7',
                            'monitored_system_id' => 1,
                            'parameter_id' => 10,
                            'multiplier' => 100,
                        ],
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ];

    $response = $this->actingAs($this->user)
        ->post(route('spreadsheet-imports.templates.store', ['current_team' => $this->team->slug]), $payload);

    $response->assertRedirect(route('spreadsheet-imports.templates.index', ['current_team' => $this->team->slug]));

    $this->assertDatabaseHas('spreadsheet_templates', [
        'team_id' => $this->team->id,
        'name' => 'Boletim Turno Água',
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);
});

test('can update an existing template', function () {
    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Nome Antigo',
        'config' => ['version' => 1, 'sheets' => []],
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('spreadsheet-imports.templates.update', [
            'current_team' => $this->team->slug,
            'template' => $template->id,
        ]), [
            'name' => 'Nome Atualizado',
            'config' => ['version' => 1, 'sheets' => [['sheet_name' => 'Nova Aba']]],
            'is_active' => false,
        ]);

    $response->assertRedirect(route('spreadsheet-imports.templates.index', ['current_team' => $this->team->slug]));

    $this->assertDatabaseHas('spreadsheet_templates', [
        'id' => $template->id,
        'name' => 'Nome Atualizado',
        'is_active' => false,
        'updated_by' => $this->user->id,
    ]);
});

test('can delete a spreadsheet template', function () {
    $template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Para Deletar',
        'config' => ['version' => 1, 'sheets' => []],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('spreadsheet-imports.templates.destroy', [
            'current_team' => $this->team->slug,
            'template' => $template->id,
        ]));

    $response->assertRedirect(route('spreadsheet-imports.templates.index', ['current_team' => $this->team->slug]));

    $this->assertSoftDeleted('spreadsheet_templates', [
        'id' => $template->id,
    ]);
});

test('cannot access template belonging to another team', function () {
    $otherTeam = Team::factory()->create();
    $otherTemplate = SpreadsheetTemplate::create([
        'team_id' => $otherTeam->id,
        'name' => 'Outro Template',
        'config' => ['version' => 1, 'sheets' => []],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('spreadsheet-imports.templates.edit', [
            'current_team' => $this->team->slug,
            'template' => $otherTemplate->id,
        ]));

    $response->assertNotFound();
});

test('can store and update template with allowed_times filter', function () {
    $payload = [
        'name' => 'Modelo com Filtro de Horas',
        'config' => [
            'version' => 1,
            'sheets' => [
                [
                    'sheet_name' => 'Caldeira',
                    'date_mode' => 'horizontal_series',
                    'date_row' => 2,
                    'time_row' => null,
                    'allowed_times' => ['07:00', '15:00', '23:00'],
                    'mappings' => [],
                ],
            ],
        ],
        'is_active' => true,
    ];

    $response = $this->actingAs($this->user)
        ->post(route('spreadsheet-imports.templates.store', ['current_team' => $this->team->slug]), $payload);

    $response->assertRedirect(route('spreadsheet-imports.templates.index', ['current_team' => $this->team->slug]));

    $template = SpreadsheetTemplate::where('name', 'Modelo com Filtro de Horas')->firstOrFail();
    expect($template->config['sheets'][0]['allowed_times'])->toBe(['07:00', '15:00', '23:00']);
});

test('can sample preview spreadsheet with columns beyond Z and rows beyond 100', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Grande');

    // Place date and values in column AA (col 27) and AC (col 29), and row 150
    $sheet->setCellValue('A2', 'Data');
    $sheet->setCellValue('AA2', '10/09/2026');
    $sheet->setCellValue('AB2', '11/09/2026');
    $sheet->setCellValue('AC2', '12/09/2026');
    $sheet->setCellValue('AC150', '99.5');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_sample_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);

    $file = new UploadedFile($tempFile, 'sample_large.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $response = $this->actingAs($this->user)
        ->post(route('spreadsheet-imports.templates.sample-preview', ['current_team' => $this->team->slug]), [
            'file' => $file,
        ]);

    $response->assertOk();
    $sheets = $response->json('sheets');
    expect($sheets)->toHaveCount(1);
    expect($sheets[0]['highest_column'])->toBe('AC');
    expect($sheets[0]['highest_row'])->toBeGreaterThanOrEqual(150);
    expect($sheets[0]['cells']['AC150'])->toEqual(99.5);
    expect($sheets[0]['cells']['AA2'])->toBe('10/09/2026');
});

test('sample preview correctly rounds and formats time cells without 1 minute difference', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Horarios');

    // 7/24 with IEEE 754 precision error: 0.29166666666666663
    $impreciseFloat = 0.29166666666666663;
    $sheet->setCellValue('B3', $impreciseFloat);
    $sheet->getStyle('B3')->getNumberFormat()->setFormatCode('hh:mm');

    // Another with serial date 45545 + 7/24
    $serialDateTime = 45545 + $impreciseFloat;
    $sheet->setCellValue('C3', $serialDateTime);
    $sheet->getStyle('C3')->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_sample_time_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);

    $file = new UploadedFile($tempFile, 'sample_time.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $response = $this->actingAs($this->user)
        ->post(route('spreadsheet-imports.templates.sample-preview', ['current_team' => $this->team->slug]), [
            'file' => $file,
        ]);

    $response->assertOk();
    $sheets = $response->json('sheets');
    // B3 must format as '07:00' and NOT '06:59'
    expect($sheets[0]['cells']['B3'])->toBe('07:00');
    // C3 must end with '07:00' and NOT '06:59'
    expect($sheets[0]['cells']['C3'])->toEndWith('07:00');
});

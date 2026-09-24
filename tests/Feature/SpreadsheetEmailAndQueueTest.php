<?php

use App\Features\SpreadsheetImports\Actions\FetchSpreadsheetEmailsAction;
use App\Features\SpreadsheetImports\Actions\ProcessSpreadsheetImportQueueAction;
use App\Features\SpreadsheetImports\Services\IncomingEmailAttachment;
use App\Features\SpreadsheetImports\Services\IncomingEmailMessage;
use App\Features\SpreadsheetImports\Services\SpreadsheetEmailReaderService;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\SpreadsheetEmailInboxItem;
use App\Models\SpreadsheetEmailRule;
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
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => 'member']);
    $this->user->update(['current_team_id' => $this->team->id]);

    $this->system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Caldeira Principal',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->paramPh = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'pH',
        'unit' => 'pH',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->template = SpreadsheetTemplate::create([
        'team_id' => $this->team->id,
        'name' => 'Template Caldeira',
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
                    ],
                ],
            ],
        ],
        'is_active' => true,
    ]);
});

function createDummyExcelContent(): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Caldeira');
    $sheet->setCellValue('B6', '2026-08-31');
    $sheet->setCellValue('C7', '10:00');
    $sheet->setCellValue('D7', '8.75');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_sheet_').'.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);
    $content = file_get_contents($tempFile);
    @unlink($tempFile);

    return $content;
}

test('can create, edit, and delete email rules', function () {
    // 1. Create rule
    $storeResp = $this->actingAs($this->user)
        ->post(route('spreadsheet-imports.rules.store', ['current_team' => $this->team->slug]), [
            'name' => 'Regra Fornecedor Caldeira',
            'spreadsheet_template_id' => $this->template->id,
            'is_active' => true,
            'priority' => 10,
            'sender_operator' => 'contains',
            'sender_value' => '@fornecedor.com',
            'subject_operator' => 'contains',
            'subject_value' => 'Relatorio Caldeira',
        ]);

    $storeResp->assertRedirect(route('spreadsheet-imports.rules.index', ['current_team' => $this->team->slug]));

    $rule = SpreadsheetEmailRule::query()
        ->where('name', 'Regra Fornecedor Caldeira')
        ->first();

    expect($rule)->not->toBeNull();
    expect($rule->team_id)->toBe($this->team->id);
    expect($rule->priority)->toBe(10);
    expect($rule->spreadsheet_template_id)->toBe($this->template->id);

    // 2. Update rule
    $updateResp = $this->actingAs($this->user)
        ->put(route('spreadsheet-imports.rules.update', [
            'current_team' => $this->team->slug,
            'rule' => $rule->id,
        ]), [
            'name' => 'Regra Fornecedor Caldeira v2',
            'spreadsheet_template_id' => $this->template->id,
            'is_active' => false,
            'priority' => 20,
            'sender_value' => '@fornecedor.com',
            'subject_value' => 'Relatorio Caldeira v2',
        ]);

    $updateResp->assertRedirect(route('spreadsheet-imports.rules.index', ['current_team' => $this->team->slug]));
    expect($rule->fresh()->name)->toBe('Regra Fornecedor Caldeira v2');
    expect($rule->fresh()->is_active)->toBeFalse();

    // 3. Delete rule
    $deleteResp = $this->actingAs($this->user)
        ->delete(route('spreadsheet-imports.rules.destroy', [
            'current_team' => $this->team->slug,
            'rule' => $rule->id,
        ]));

    $deleteResp->assertRedirect(route('spreadsheet-imports.rules.index', ['current_team' => $this->team->slug]));
    expect($rule->fresh()->trashed())->toBeTrue();
});

test('fetch emails action matches active rules and captures spreadsheet attachments', function () {
    $rule = SpreadsheetEmailRule::create([
        'team_id' => $this->team->id,
        'spreadsheet_template_id' => $this->template->id,
        'name' => 'Captura Relatorio Caldeira',
        'is_active' => true,
        'priority' => 5,
        'sender_operator' => 'contains',
        'sender_value' => 'operador@empresa.com',
        'subject_operator' => 'contains',
        'subject_value' => 'Medicao Diaria',
    ]);

    $excelContent = createDummyExcelContent();

    // Mock reader service
    $mockReader = Mockery::mock(SpreadsheetEmailReaderService::class);
    $mockReader->shouldReceive('fetchUnreadEmails')->once()->andReturn([
        new IncomingEmailMessage(
            messageId: 'msg-123',
            senderEmail: 'operador@empresa.com',
            senderName: 'Operador Fabril',
            subject: 'Medicao Diaria - Caldeira 2026-08-31',
            bodyText: 'Segue em anexo a planilha de medicao',
            date: new DateTimeImmutable('2026-08-31 11:00:00'),
            attachments: [
                new IncomingEmailAttachment(
                    filename: 'medicao_2026-08-31.xlsx',
                    content: $excelContent,
                    mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    sizeBytes: strlen($excelContent),
                ),
            ],
        ),
        // An email that does not match
        new IncomingEmailMessage(
            messageId: 'msg-456',
            senderEmail: 'spam@outro.com',
            senderName: 'Outro',
            subject: 'Oferta Especial',
            bodyText: 'Spam body',
            date: new DateTimeImmutable('2026-08-31 12:00:00'),
            attachments: [
                new IncomingEmailAttachment(
                    filename: 'spam.xlsx',
                    content: $excelContent,
                    mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    sizeBytes: strlen($excelContent),
                ),
            ],
        ),
    ]);

    $this->app->instance(SpreadsheetEmailReaderService::class, $mockReader);

    $action = app(FetchSpreadsheetEmailsAction::class);
    $result = $action->execute();

    expect($result['emails_checked'])->toBe(2);
    expect($result['attachments_captured'])->toBe(1);

    $inboxItem = SpreadsheetEmailInboxItem::query()->first();
    expect($inboxItem)->not->toBeNull();
    expect($inboxItem->team_id)->toBe($this->team->id);
    expect($inboxItem->spreadsheet_email_rule_id)->toBe($rule->id);
    expect($inboxItem->spreadsheet_template_id)->toBe($this->template->id);
    expect($inboxItem->sender_email)->toBe('operador@empresa.com');
    expect($inboxItem->file_name)->toBe('medicao_2026-08-31.xlsx');
    expect($inboxItem->extracted_reference_date?->format('Y-m-d'))->toBe('2026-08-31');
    expect($inboxItem->status)->toBe('pending_confirmation');

    Storage::disk('local')->assertExists($inboxItem->file_path);
});

test('can update, preview, enqueue, and discard pending confirmation items', function () {
    $excelContent = createDummyExcelContent();
    $filePath = 'spreadsheet_inbox/test_sample.xlsx';
    Storage::disk('local')->put($filePath, $excelContent);

    $inboxItem = SpreadsheetEmailInboxItem::create([
        'team_id' => $this->team->id,
        'spreadsheet_template_id' => $this->template->id,
        'sender_email' => 'operador@empresa.com',
        'subject' => 'Planilha de Teste',
        'file_name' => 'test_sample.xlsx',
        'file_path' => $filePath,
        'file_size_bytes' => strlen($excelContent),
        'extracted_reference_date' => '2026-08-31',
        'status' => 'pending_confirmation',
    ]);

    // 1. Preview pending item
    $previewResp = $this->actingAs($this->user)
        ->getJson(route('spreadsheet-imports.confirmations.preview', [
            'current_team' => $this->team->slug,
            'item' => $inboxItem->id,
        ]));

    $previewResp->assertOk();
    $previewResp->assertJson(['success' => true]);
    expect($previewResp->json('preview.total_extracted'))->toBe(1);

    // 2. Update inline
    $updateResp = $this->actingAs($this->user)
        ->putJson(route('spreadsheet-imports.confirmations.update', [
            'current_team' => $this->team->slug,
            'item' => $inboxItem->id,
        ]), [
            'spreadsheet_template_id' => $this->template->id,
            'reference_date' => '2026-09-01',
        ]);

    $updateResp->assertOk();
    expect($inboxItem->fresh()->extracted_reference_date->format('Y-m-d'))->toBe('2026-09-01');

    // 3. Enqueue item
    $enqueueResp = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.confirmations.enqueue', [
            'current_team' => $this->team->slug,
            'item' => $inboxItem->id,
        ]), [
            'spreadsheet_template_id' => $this->template->id,
            'reference_date' => '2026-09-01',
        ]);

    $enqueueResp->assertOk();
    expect($inboxItem->fresh()->status)->toBe('queued');

    $queueItem = SpreadsheetImportQueue::query()->where('spreadsheet_email_inbox_item_id', $inboxItem->id)->first();
    expect($queueItem)->not->toBeNull();
    expect($queueItem->status)->toBe('pending');
    expect($queueItem->source_type)->toBe('email');
    expect($queueItem->reference_date?->format('Y-m-d'))->toBe('2026-09-01');
});

test('process queue command imports items and saves parameter values', function () {
    $excelContent = createDummyExcelContent();
    $filePath = 'spreadsheet_inbox/test_process.xlsx';
    Storage::disk('local')->put($filePath, $excelContent);

    $queueItem = SpreadsheetImportQueue::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'spreadsheet_template_id' => $this->template->id,
        'source_type' => 'email',
        'file_name' => 'test_process.xlsx',
        'file_path' => $filePath,
        'reference_date' => '2026-08-31',
        'status' => 'pending',
    ]);

    $action = app(ProcessSpreadsheetImportQueueAction::class);
    $result = $action->execute();

    expect($result['processed_count'])->toBe(1);
    expect($result['success_count'])->toBe(1);
    expect($result['failed_count'])->toBe(0);

    expect($queueItem->fresh()->status)->toBe('completed');
    expect($queueItem->fresh()->saved_values_count)->toBe(1);
    expect($queueItem->fresh()->spreadsheet_import_batch_id)->not->toBeNull();

    $this->assertDatabaseHas('parameter_values', [
        'team_id' => $this->team->id,
        'parameter_id' => $this->paramPh->id,
        'value' => 8.75,
        'source_type' => 'spreadsheet',
    ]);
});

test('can enqueue manual spreadsheet upload and process it via queue', function () {
    $file = UploadedFile::fake()->createWithContent('manual_test.xlsx', createDummyExcelContent());

    $response = $this->actingAs($this->user)
        ->postJson(route('spreadsheet-imports.enqueue-manual', ['current_team' => $this->team->slug]), [
            'spreadsheet_template_id' => $this->template->id,
            'reference_date' => '2026-08-31',
            'file' => $file,
        ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $queueItem = SpreadsheetImportQueue::query()
        ->where('source_type', 'manual_upload')
        ->first();

    expect($queueItem)->not->toBeNull();
    expect($queueItem->status)->toBe('pending');
    expect($queueItem->team_id)->toBe($this->team->id);

    // Process queue
    $action = app(ProcessSpreadsheetImportQueueAction::class);
    $result = $action->execute();

    expect($result['success_count'])->toBe(1);
    expect($queueItem->fresh()->status)->toBe('completed');
});

<?php

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->member = User::factory()->create(['is_admin' => false]);
    $this->team = Team::factory()->create();
    $this->team->members()->attach([$this->admin->id, $this->member->id], ['role' => 'admin']);
    $this->system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Água clarificada',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $this->parameter = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'pH',
        'unit' => 'pH',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $this->document = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => 'Operação normal.']],
        ]],
    ];
});

test('only administrators can access the report editor', function () {
    $parameters = [
        'current_team' => $this->team,
        'date_reference' => '2026-07-28',
        'system_id' => $this->system->id,
    ];

    $this->actingAs($this->member)
        ->getJson(route('reports.editor.show', $parameters))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->getJson(route('reports.editor.show', $parameters))
        ->assertOk()
        ->assertJsonPath('state', 'new_report')
        ->assertJsonPath('system.id', $this->system->id);
});

test('first save creates a draft and repeated saves are idempotent while sanitizing html', function () {
    $payload = [
        'date_reference' => '2026-07-28',
        'system_id' => $this->system->id,
        'document' => $this->document,
        'html' => '<p onclick="alert(1)">Operação normal.</p><script>alert(2)</script>',
        'hide_data' => true,
        'is_stopped' => false,
    ];

    $response = $this->actingAs($this->admin)
        ->putJson(route('reports.editor.update', $this->team), $payload)
        ->assertOk()
        ->assertJsonPath('state', 'existing');

    expect(Report::count())->toBe(1)
        ->and(ReportItem::count())->toBe(1);

    $report = Report::firstOrFail();
    $item = ReportItem::firstOrFail();

    expect($report->status)->toBe('draft')
        ->and($report->title)->toBe('Relatório 28/07/2026')
        ->and($item->comment)->toContain('Operação normal.')
        ->and($item->comment)->not->toContain('onclick')
        ->and($item->comment)->not->toContain('<script')
        ->and($item->metadata['editor']['document'])->toBe($this->document)
        ->and($item->hide_data)->toBeTrue();

    $this->actingAs($this->admin)
        ->putJson(route('reports.editor.update', $this->team), [
            ...$payload,
            'expected_updated_at' => $response->json('item.updated_at'),
        ])
        ->assertOk();

    expect(Report::count())->toBe(1)
        ->and(ReportItem::count())->toBe(1);
});

test('completed legacy reports remain completed and stale edits return a conflict', function () {
    $report = Report::create([
        'team_id' => $this->team->id,
        'created_by' => $this->admin->id,
        'title' => 'Legado',
        'date_reference' => '2026-07-28',
        'status' => 'completed',
    ]);
    $item = ReportItem::create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-07-28',
        'comment' => '<p>Anterior</p>',
        'metadata' => ['legacy_key' => 'preservar'],
    ]);

    $this->actingAs($this->admin)
        ->putJson(route('reports.editor.update', $this->team), [
            'date_reference' => '2026-07-28',
            'system_id' => $this->system->id,
            'document' => $this->document,
            'html' => '<p>Atualizado</p>',
            'hide_data' => false,
            'is_stopped' => true,
            'expected_updated_at' => $item->updated_at->subMinute()->toIso8601String(),
        ])
        ->assertConflict();

    $this->actingAs($this->admin)
        ->putJson(route('reports.editor.update', $this->team), [
            'date_reference' => '2026-07-28',
            'system_id' => $this->system->id,
            'document' => $this->document,
            'html' => '<p>Atualizado</p>',
            'hide_data' => false,
            'is_stopped' => true,
            'force' => true,
        ])
        ->assertOk();

    expect($report->refresh()->status)->toBe('completed')
        ->and($item->refresh()->metadata['legacy_key'])->toBe('preservar')
        ->and($item->metadata['editor']['schema_version'])->toBe(1);
});

test('report resources are isolated by team and chart data is constrained to the route team', function () {
    $otherTeam = Team::factory()->create();
    $otherSystem = MonitoredSystem::create([
        'team_id' => $otherTeam->id,
        'name' => 'Outro sistema',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $otherParameter = Parameter::create([
        'team_id' => $otherTeam->id,
        'monitored_system_id' => $otherSystem->id,
        'name' => 'Ferro',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('reports.editor.show', [
            'current_team' => $this->team,
            'date_reference' => '2026-07-28',
            'system_id' => $otherSystem->id,
        ]))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->postJson(route('reports.charts.data', $this->team), [
            'start_date' => '2026-07-22',
            'end_date' => '2026-07-28',
            'series' => [[
                'parameter_id' => $otherParameter->id,
                'chart_type' => 'line',
                'color' => '#2563EB',
                'stroke_width' => 2,
                'axis_position' => 'left',
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('series');
});

test('history keeps complete documents and grouping synchronizes content but not flags', function () {
    $olderReport = Report::create([
        'team_id' => $this->team->id,
        'created_by' => $this->admin->id,
        'title' => 'Anterior',
        'date_reference' => '2026-07-20',
        'status' => 'completed',
    ]);
    ReportItem::create([
        'team_id' => $this->team->id,
        'report_id' => $olderReport->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-07-20',
        'comment' => '<p><img class="img-report-ok img-report" src="/storage/legacy/ok.jpg"> Comentário anterior</p>',
        'metadata' => ['editor' => ['schema_version' => 1, 'document' => $this->document]],
    ]);

    $history = $this->actingAs($this->admin)
        ->getJson(route('reports.history.index', [
            'current_team' => $this->team,
            'system_id' => $this->system->id,
            'before_date' => '2026-07-28',
        ]))
        ->assertOk();
    expect($history->json('items.0.document'))->toBe($this->document);
    expect($history->json('items.0.html'))
        ->toContain('data-report-icon="success"')
        ->not->toContain('<img');

    $secondSystem = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Água bruta',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $report = Report::create([
        'team_id' => $this->team->id,
        'created_by' => $this->admin->id,
        'title' => 'Atual',
        'date_reference' => '2026-07-28',
        'status' => 'draft',
    ]);
    $source = ReportItem::create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-07-28',
        'hide_data' => true,
        'comment' => '<p>Fonte</p>',
        'metadata' => ['editor' => ['schema_version' => 1, 'document' => $this->document]],
    ]);
    $member = ReportItem::create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $secondSystem->id,
        'date_reference' => '2026-07-28',
        'is_stopped' => true,
        'comment' => '<p>Diferente</p>',
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('reports.groups.store', $this->team), [
            'report_id' => $report->id,
            'item_ids' => [$source->id, $member->id],
            'source_item_id' => $source->id,
        ])
        ->assertOk();

    expect($member->refresh()->parent_report_item_id)->toBe($source->id)
        ->and($member->comment)->toBe($source->comment)
        ->and($member->is_stopped)->toBeTrue()
        ->and($source->refresh()->hide_data)->toBeTrue();

    $this->actingAs($this->admin)
        ->deleteJson(route('reports.groups.destroy', $this->team), ['report_item_id' => $member->id])
        ->assertOk();

    expect($member->refresh()->parent_report_item_id)->toBeNull();
});

test('assistant uses Gemini without persisting the conversation', function () {
    config([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test-model',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'message' => 'Analisei a solicitação.',
                    'suggestion' => '**Parâmetros** <b>estáveis</b>.',
                    'tool_calls' => [],
                ])]]],
            ]],
        ]),
    ]);
    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'parameter_id' => $this->parameter->id,
        'measured_at' => '2026-07-28 08:00:00',
        'measured_date' => '2026-07-28',
        'value' => 7.2,
        'source_type' => 'manual',
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('reports.assistant.store', $this->team), [
            'date_reference' => '2026-07-28',
            'system_id' => $this->system->id,
            'current_text' => 'Texto atual',
            'messages' => [['role' => 'user', 'content' => 'Faça um resumo']],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Analisei a solicitação.')
        ->assertJsonPath('suggestion', 'Parâmetros estáveis.')
        ->assertJsonPath('suggestion_document.type', 'doc')
        ->assertJsonPath('suggestion_document.content.0.content.0.type', 'reportIcon')
        ->assertJsonPath('suggestion_document.content.0.content.2.type', 'parameterReference')
        ->assertJsonPath('suggestion_document.content.1.type', 'reportChart')
        ->assertJsonPath('activity', []);

    Http::assertSent(fn ($request) => str_contains($request->body(), 'Texto atual')
        && str_contains($request->body(), '28\/07\/2026'));
    expect(Report::count())->toBe(0)
        ->and(ReportItem::count())->toBe(0);
});

test('assistant can choose a team scoped operational data tool', function () {
    config([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test-model',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->push([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'message' => 'Vou consultar os dados.',
                        'suggestion' => null,
                        'tool_calls' => [[
                            'name' => 'get_operational_metrics',
                            'arguments' => [
                                'system_id' => $this->system->id,
                                'start_date' => '2026-07-28',
                                'end_date' => '2026-07-28',
                            ],
                        ]],
                    ])]]],
                ]],
            ])
            ->push([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'message' => 'O pH teve média 7,2.',
                        'suggestion' => 'O pH permaneceu estável, com média de 7,2.',
                        'tool_calls' => [],
                    ])]]],
                ]],
            ]),
    ]);
    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'parameter_id' => $this->parameter->id,
        'measured_at' => '2026-07-28 08:00:00',
        'measured_date' => '2026-07-28',
        'value' => 7.2,
        'source_type' => 'manual',
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('reports.assistant.store', $this->team), [
            'date_reference' => '2026-07-28',
            'system_id' => $this->system->id,
            'messages' => [['role' => 'user', 'content' => 'Qual foi a média do pH?']],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'O pH teve média 7,2.')
        ->assertJsonPath('activity.0.tool', 'get_operational_metrics')
        ->assertJsonPath('activity.0.label', 'Consultou dados de 28/07/2026 a 28/07/2026 em Água clarificada')
        ->assertJsonPath('suggestion', 'O pH permaneceu estável, com média de 7,2.');

    Http::assertSentCount(2);
    expect(Report::count())->toBe(0)
        ->and(ReportItem::count())->toBe(0);
});

<?php

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\ReportRecipient;
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

test('saving a consideration with report charts preserves config and resolves chart data across report views', function () {
    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'parameter_id' => $this->parameter->id,
        'measured_at' => '2026-07-28 08:00:00',
        'measured_date' => '2026-07-28',
        'value' => 7.25,
        'source_type' => 'manual',
    ]);

    $otherTeam = Team::factory()->create();
    $otherSystem = MonitoredSystem::create([
        'team_id' => $otherTeam->id,
        'name' => 'Sistema externo',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $otherParameter = Parameter::create([
        'team_id' => $otherTeam->id,
        'monitored_system_id' => $otherSystem->id,
        'name' => 'Externo',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $chartAttrs = [
        'id' => 'chart-uuid-1',
        'title' => 'Água de Alimentação - CACE x DCACE',
        'height' => 320,
        'startDate' => '2026-07-22',
        'endDate' => '2026-07-28',
        'teamSlug' => $this->team->slug,
        'series' => [
            [
                'parameter_id' => $this->parameter->id,
                'label' => 'pH',
                'chart_type' => 'line',
                'color' => '#2563EB',
                'stroke_width' => 2,
                'axis_position' => 'left',
                'min_val' => null,
                'max_val' => null,
                'show_points' => true,
                'show_values' => false,
            ],
            [
                'parameter_id' => $otherParameter->id,
                'label' => 'Externo bloqueado',
                'chart_type' => 'line',
                'color' => '#E11D48',
                'stroke_width' => 2,
                'axis_position' => 'right',
                'min_val' => null,
                'max_val' => null,
                'show_points' => true,
                'show_values' => false,
            ],
        ],
    ];

    $document = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'teste']],
            ],
            [
                'type' => 'reportChart',
                'attrs' => $chartAttrs,
            ],
        ],
    ];

    $encodedConfig = htmlspecialchars((string) json_encode($chartAttrs), ENT_QUOTES, 'UTF-8');
    $html = '<p>teste</p><div data-report-chart="chart-uuid-1" data-chart-id="chart-uuid-1" data-chart-config="'.$encodedConfig.'" onclick="alert(1)">Gráfico: Água de Alimentação - CACE x DCACE</div>';

    $saveResponse = $this->actingAs($this->admin)
        ->putJson(route('reports.editor.update', $this->team), [
            'date_reference' => '2026-07-28',
            'system_id' => $this->system->id,
            'document' => $document,
            'html' => $html,
            'hide_data' => false,
            'is_stopped' => false,
        ]);

    $saveResponse->assertOk()
        ->assertJsonPath('item.document.content.1.attrs.id', 'chart-uuid-1')
        ->assertJsonCount(1, 'item.charts.chart-uuid-1')
        ->assertJsonPath('item.charts.chart-uuid-1.0.data.0.value', 7.25);

    $item = ReportItem::firstOrFail();
    expect($item->comment)->toContain('data-report-chart="chart-uuid-1"')
        ->and($item->comment)->toContain('data-chart-config=')
        ->and($item->comment)->not->toContain('onclick');

    $report = Report::firstOrFail();
    $report->update(['status' => 'completed', 'finished_at' => now()]);

    $this->actingAs($this->admin)
        ->get(route('reports.show', [$this->team, $report]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Show')
            ->where('systems.0.item.document.content.1.attrs.id', 'chart-uuid-1')
            ->has('systems.0.item.charts.chart-uuid-1', 1)
            ->where('systems.0.item.charts.chart-uuid-1.0.data.0.value', 7.25));

    $this->actingAs($this->admin)
        ->get(route('reports.preview', [$this->team, $report]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Preview')
            ->has('systems.0.item.charts.chart-uuid-1', 1)
            ->where('systems.0.item.charts.chart-uuid-1.0.data.0.value', 7.25));

    $this->actingAs($this->member)
        ->get(route('reports.user.show', [$this->team, $report]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/UserShow')
            ->has('systems.0.item.charts.chart-uuid-1', 1)
            ->where('systems.0.item.charts.chart-uuid-1.0.data.0.value', 7.25));

    $token = 'chart-public-token';
    ReportRecipient::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'user_id' => $this->member->id,
        'name' => $this->member->name,
        'email' => $this->member->email,
        'access_token' => $token,
        'token_hash' => hash('sha256', $token),
    ]);

    $this->get(route('reports.public.show', $token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Public')
            ->has('systems.0.item.charts.chart-uuid-1', 1)
            ->where('systems.0.item.charts.chart-uuid-1.0.data.0.value', 7.25));
});

test('report editor preserves safe color and highlight styles while stripping unsafe css and provides previous hide_data default', function () {
    $previousReport = Report::create([
        'team_id' => $this->team->id,
        'created_by' => $this->admin->id,
        'title' => 'Relatório anterior',
        'date_reference' => '2026-07-27',
        'status' => 'completed',
    ]);
    ReportItem::create([
        'team_id' => $this->team->id,
        'report_id' => $previousReport->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-07-27',
        'show_data_results' => true,
        'hide_data' => true,
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('reports.editor.show', [
            'current_team' => $this->team,
            'date_reference' => '2026-07-28',
            'system_id' => $this->system->id,
        ]))
        ->assertOk()
        ->assertJsonPath('default_hide_data', true);

    $html = '<p><span style="color: #ec4899">Rosa</span> <mark data-color="#fecdd3" style="background-color: #fecdd3; color: inherit">Destaque</mark> <span style="position: fixed; background-image: url(https://evil.test); color: #0f172a">Seguro</span></p>';

    $this->actingAs($this->admin)
        ->putJson(route('reports.editor.update', $this->team), [
            'date_reference' => '2026-07-28',
            'system_id' => $this->system->id,
            'document' => $this->document,
            'html' => $html,
            'hide_data' => true,
            'is_stopped' => false,
        ])
        ->assertOk();

    $item = ReportItem::query()->whereDate('date_reference', '2026-07-28')->firstOrFail();

    expect($item->comment)
        ->toContain('color: #ec4899')
        ->toContain('data-color="#fecdd3"')
        ->toContain('background-color: #fecdd3')
        ->toContain('color: #0f172a')
        ->not->toContain('position')
        ->not->toContain('background-image')
        ->not->toContain('evil.test');
});

test('chart data resolves parameter limits with first series enabled by default and respects show_limits selection', function () {
    $this->parameter->update([
        'alert_1_min' => 6.5,
        'alert_1_max' => 8.5,
    ]);

    $secondParameter = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'Cloro',
        'unit' => 'mg/L',
        'sort_order' => 2,
        'alert_1_min' => 0.5,
        'alert_1_max' => 2.0,
        'is_active' => true,
    ]);

    // Legacy payload without show_limits: defaults to 1st series only using parameter alert_1 limits
    $this->actingAs($this->admin)
        ->postJson(route('reports.charts.data', $this->team), [
            'start_date' => '2026-07-22',
            'end_date' => '2026-07-28',
            'series' => [
                [
                    'parameter_id' => $this->parameter->id,
                    'label' => 'pH',
                    'chart_type' => 'line',
                    'color' => '#2563EB',
                    'stroke_width' => 2,
                    'axis_position' => 'left',
                    'min_val' => null,
                    'max_val' => null,
                ],
                [
                    'parameter_id' => $secondParameter->id,
                    'label' => 'Cloro',
                    'chart_type' => 'line',
                    'color' => '#E11D48',
                    'stroke_width' => 2,
                    'axis_position' => 'right',
                    'min_val' => null,
                    'max_val' => null,
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('series.0.show_limits', true)
        ->assertJsonPath('series.0.min_val', 6.5)
        ->assertJsonPath('series.0.max_val', 8.5)
        ->assertJsonPath('series.1.show_limits', false)
        ->assertJsonPath('series.1.min_val', null)
        ->assertJsonPath('series.1.max_val', null);

    // Explicit show_limits: user hides 1st parameter limits and enables 2nd parameter limits.
    // Even if custom min_val/max_val are present in payload, fixed panel alert_1 limits are always used.
    $this->actingAs($this->admin)
        ->postJson(route('reports.charts.data', $this->team), [
            'start_date' => '2026-07-22',
            'end_date' => '2026-07-28',
            'series' => [
                [
                    'parameter_id' => $this->parameter->id,
                    'label' => 'pH',
                    'chart_type' => 'line',
                    'color' => '#2563EB',
                    'stroke_width' => 2,
                    'axis_position' => 'left',
                    'min_val' => 6.5,
                    'max_val' => 8.5,
                    'show_limits' => false,
                ],
                [
                    'parameter_id' => $secondParameter->id,
                    'label' => 'Cloro',
                    'chart_type' => 'line',
                    'color' => '#E11D48',
                    'stroke_width' => 2,
                    'axis_position' => 'right',
                    'min_val' => 99.0,
                    'max_val' => 199.0,
                    'show_limits' => true,
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('series.0.show_limits', false)
        ->assertJsonPath('series.0.min_val', null)
        ->assertJsonPath('series.0.max_val', null)
        ->assertJsonPath('series.1.show_limits', true)
        ->assertJsonPath('series.1.min_val', 0.5)
        ->assertJsonPath('series.1.max_val', 2);
});

test('report chart saves and preserves per-axis min and max configuration', function () {
    $chartAttrs = [
        'id' => 'chart-axes-1',
        'title' => 'Gráfico com Eixos Customizados',
        'height' => 320,
        'startDate' => '2026-07-22',
        'endDate' => '2026-07-28',
        'teamSlug' => $this->team->slug,
        'axes' => [
            'left' => ['min' => 0, 'max' => 14],
            'right' => ['min' => 0.1, 'max' => 3.5],
        ],
        'series' => [
            [
                'parameter_id' => $this->parameter->id,
                'label' => 'pH',
                'chart_type' => 'line',
                'color' => '#2563EB',
                'stroke_width' => 2,
                'axis_position' => 'left',
                'min_val' => null,
                'max_val' => null,
                'show_limits' => true,
                'show_points' => true,
                'show_values' => false,
            ],
        ],
    ];

    $document = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'reportChart',
                'attrs' => $chartAttrs,
            ],
        ],
    ];

    $encodedConfig = htmlspecialchars((string) json_encode($chartAttrs), ENT_QUOTES, 'UTF-8');
    $html = '<div data-report-chart="chart-axes-1" data-chart-id="chart-axes-1" data-chart-config="'.$encodedConfig.'">Gráfico: Gráfico com Eixos Customizados</div>';

    $this->actingAs($this->admin)
        ->putJson(route('reports.editor.update', $this->team), [
            'date_reference' => '2026-07-28',
            'system_id' => $this->system->id,
            'document' => $document,
            'html' => $html,
            'hide_data' => false,
            'is_stopped' => false,
        ])
        ->assertOk()
        ->assertJsonPath('item.document.content.0.attrs.axes.left.min', 0)
        ->assertJsonPath('item.document.content.0.attrs.axes.left.max', 14)
        ->assertJsonPath('item.document.content.0.attrs.axes.right.min', 0.1)
        ->assertJsonPath('item.document.content.0.attrs.axes.right.max', 3.5);
});

<?php

use App\Infrastructure\AI\GeminiService;
use App\Jobs\SendReportRecipientEmail;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Report;
use App\Models\ReportActivity;
use App\Models\ReportComment;
use App\Models\ReportItem;
use App\Models\ReportItemRevision;
use App\Models\ReportRecipient;
use App\Models\ReportSuggestion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->member = User::factory()->create(['is_admin' => false]);
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->admin, ['role' => 'admin']);
    $this->team->members()->attach($this->member, ['role' => 'member']);
    $this->admin->update(['current_team_id' => $this->team->id]);
    $this->system = MonitoredSystem::query()->create([
        'team_id' => $this->team->id,
        'name' => 'Água bruta',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $this->otherSystem = MonitoredSystem::query()->create([
        'team_id' => $this->team->id,
        'name' => 'Água tratada',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $this->parameter = Parameter::query()->create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'Turbidez',
        'unit' => 'NTU',
        'decimals' => 2,
        'alert_1_min' => 0,
        'alert_1_max' => 5,
        'sort_order' => 1,
        'is_active' => true,
    ]);
});

function createConsolidatedReport(object $test): Report
{
    $test->actingAs($test->admin)
        ->post(route('reports.store', $test->team), ['date_reference' => '2026-08-04'])
        ->assertRedirect();

    return Report::query()->firstOrFail();
}

test('administrator creates one report per date and sees every active system', function () {
    $report = createConsolidatedReport($this);

    $this->actingAs($this->admin)
        ->post(route('reports.store', $this->team), ['date_reference' => '2026-08-04'])
        ->assertRedirect(route('reports.show', [$this->team, $report]));

    expect(Report::query()->count())->toBe(1)
        ->and(ReportActivity::query()->where('action', 'report.created')->count())->toBe(1);

    $this->actingAs($this->admin)
        ->get(route('reports.show', [$this->team, $report]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Show')
            ->has('systems', 2)
            ->has('intendedRecipients', 2)
            ->where('systems.0.name', 'Água bruta')
            ->where('systems.0.item', null));

    $this->actingAs($this->member)
        ->get(route('reports.show', [$this->team, $report]))
        ->assertForbidden();
});

test('report index lists only reports from the current company and filters correctly', function () {
    $report = createConsolidatedReport($this);
    $reportCompleted = Report::query()->create([
        'team_id' => $this->team->id,
        'created_by' => $this->admin->id,
        'title' => 'Relatório Especial',
        'date_reference' => '2026-08-01',
        'status' => 'completed',
    ]);
    $otherTeam = Team::factory()->create();
    Report::query()->create([
        'team_id' => $otherTeam->id,
        'created_by' => $this->admin->id,
        'title' => 'Relatório externo',
        'date_reference' => '2026-08-05',
        'status' => 'draft',
    ]);

    $this->actingAs($this->admin)
        ->get(route('reports.index', $this->team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 2));

    $this->actingAs($this->admin)
        ->get(route('reports.index', ['current_team' => $this->team, 'status' => 'completed']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 1)
            ->where('reports.data.0.id', $reportCompleted->id));

    $this->actingAs($this->admin)
        ->get(route('reports.index', ['current_team' => $this->team, 'search' => 'Especial']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 1)
            ->where('reports.data.0.id', $reportCompleted->id));

    $this->actingAs($this->member)
        ->get(route('reports.index', $this->team))
        ->assertForbidden();
});

test('visibility is explicit and saving a consideration enables the system with a revision', function () {
    $report = createConsolidatedReport($this);

    $this->actingAs($this->admin)
        ->putJson(route('reports.systems.update', [$this->team, $report]), [
            'system_id' => $this->system->id,
            'show_data_results' => false,
        ])->assertOk()->assertJsonPath('show_data_results', false);

    $item = ReportItem::query()->firstOrFail();
    expect($item->show_data_results)->toBeFalse();

    $this->actingAs($this->admin)
        ->putJson(route('reports.editor.update', $this->team), [
            'date_reference' => '2026-08-04',
            'system_id' => $this->system->id,
            'document' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Operação normal.']],
                ]],
            ],
            'html' => '<p>Operação normal.</p>',
            'hide_data' => false,
            'is_stopped' => false,
        ])->assertOk()->assertJsonPath('item.show_data_results', true);

    expect($item->refresh()->show_data_results)->toBeTrue()
        ->and(ReportItemRevision::query()->whereBelongsTo($item)->count())->toBeGreaterThanOrEqual(2)
        ->and(ReportActivity::query()->where('action', 'consideration.updated')->exists())->toBeTrue();
});

test('system data and seven day charts are scoped to the report company', function () {
    $report = createConsolidatedReport($this);
    ParameterValue::query()->create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'parameter_id' => $this->parameter->id,
        'measured_at' => '2026-08-04 07:00:00',
        'measured_date' => '2026-08-04',
        'value' => 4.17,
        'source_type' => 'manual',
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('reports.systems.data', [$this->team, $report, $this->system]))
        ->assertOk()
        ->assertJsonPath('parameters.0.alert_1_max', 5)
        ->assertJsonPath('rows.0.values.'.$this->parameter->id, 4.17);

    $this->actingAs($this->admin)
        ->getJson(route('reports.systems.chart', [
            $this->team,
            $report,
            $this->system,
            'parameter_id' => $this->parameter->id,
            'type' => 'bar',
        ]))->assertOk()
        ->assertJsonPath('series.0.chart_type', 'bar')
        ->assertJsonPath('series.0.data.0.value', 4.17);
});

test('finalization snapshots active members into individual recipients and queues delivery jobs once', function () {
    $report = createConsolidatedReport($this);
    ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => true,
    ]);
    Queue::fake();

    $this->actingAs($this->admin)
        ->post(route('reports.finalize', [$this->team, $report]))
        ->assertRedirect();

    expect($report->refresh()->status)->toBe('completed')
        ->and(ReportRecipient::query()->count())->toBe(2)
        ->and(ReportRecipient::query()->pluck('token_hash')->unique()->count())->toBe(2);
    Queue::assertPushed(SendReportRecipientEmail::class, 2);

    $this->actingAs($this->admin)
        ->post(route('reports.finalize', [$this->team, $report]))
        ->assertRedirect();
    Queue::assertPushed(SendReportRecipientEmail::class, 2);
});

test('public token identifies comments and stops working after membership removal', function () {
    $report = createConsolidatedReport($this);
    $item = ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => true,
        'comment' => '<p>Resultados estáveis.</p>',
    ]);
    $report->update(['status' => 'completed', 'finished_at' => now()]);
    $token = 'public-token-for-member';
    $recipient = ReportRecipient::query()->create([
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
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Public')
            ->has('systems', 1)
            ->where('recipient.email', $this->member->email));

    $this->post(route('reports.public.comments.store', $token), [
        'report_item_id' => $item->id,
        'body' => '<script>alert(1)</script> Minha observação',
        'email' => 'spoof@example.com',
    ])->assertRedirect();

    $comment = ReportComment::query()->firstOrFail();
    expect($comment->report_recipient_id)->toBe($recipient->id)
        ->and($comment->user_id)->toBe($this->member->id)
        ->and($comment->body)->toBe('<script>alert(1)</script> Minha observação');

    $this->team->members()->detach($this->member);
    $this->get(route('reports.public.show', $token))->assertForbidden();
});

test('administrator can preview client report and disabled systems are omitted', function () {
    $report = createConsolidatedReport($this);
    $report->update(['status' => 'completed', 'finished_at' => now()]);

    $enabledItem = ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => true,
        'comment' => '<p>Sistema ativo</p>',
    ]);

    $disabledItem = ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->otherSystem->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => false,
        'comment' => '<p>Sistema desabilitado</p>',
    ]);

    // Member cannot access admin preview
    $this->actingAs($this->member)
        ->get(route('reports.preview', [$this->team, $report]))
        ->assertForbidden();

    // Admin can access preview
    $this->actingAs($this->admin)
        ->get(route('reports.preview', [$this->team, $report]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Preview')
            ->where('isPreview', true)
            ->has('systems', 1)
            ->where('systems.0.id', $this->system->id)
            ->where('recipient.email', $this->admin->email));

    // Admin can comment in preview
    $this->actingAs($this->admin)
        ->post(route('reports.preview.comments.store', [$this->team, $report]), [
            'report_item_id' => $enabledItem->id,
            'body' => 'Comentário do admin em modo preview',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('report_comments', [
        'report_item_id' => $enabledItem->id,
        'user_id' => $this->admin->id,
        'body' => 'Comentário do admin em modo preview',
    ]);
});

test('report agent creates reviewable corrections and applies only an accepted current suggestion', function () {
    $report = createConsolidatedReport($this);
    $item = ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => true,
        'comment' => '<p>Os resultado está normal.</p>',
    ]);
    app()->instance(GeminiService::class, new class extends GeminiService
    {
        public function __construct() {}

        public function generateJson(string $prompt, array $options = []): ?array
        {
            return ['suggestions' => [[
                'item_id' => ReportItem::query()->value('id'),
                'original_text' => 'resultado está',
                'replacement_text' => 'resultados estão',
                'reason' => 'Concordância nominal e verbal.',
            ]]];
        }
    });

    $this->actingAs($this->admin)
        ->postJson(route('reports.suggestions.store', [$this->team, $report]), [
            'mode' => 'proofread',
        ])->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('suggestions.0.system_id', $this->system->id);

    $suggestion = ReportSuggestion::query()->firstOrFail();
    expect($item->refresh()->comment)->toContain('resultado está');

    $this->actingAs($this->admin)
        ->patchJson(route('reports.suggestions.update', [$this->team, $report, $suggestion]), [
            'decision' => 'accept',
        ])->assertOk()->assertJsonPath('status', 'accepted');

    expect($item->refresh()->comment)->toContain('resultados estão')
        ->and(ReportActivity::query()->where('action', 'suggestion.accepted')->exists())->toBeTrue();
});

test('consolidated report agent creates suggestions only for enabled systems and ignores disabled systems', function () {
    $report = createConsolidatedReport($this);

    // Enabled system item
    $enabledItem = ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => true,
        'comment' => '<p>Operação estável no período.</p>',
    ]);

    // Disabled system item
    $disabledItem = ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->otherSystem->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => false,
        'comment' => '<p>Sistema desabilitado.</p>',
    ]);

    app()->instance(GeminiService::class, new class($enabledItem, $disabledItem) extends GeminiService
    {
        public function __construct(private $enabledItem, private $disabledItem) {}

        public function generateJson(string $prompt, array $options = []): ?array
        {
            return ['suggestions' => [
                [
                    'item_id' => $this->enabledItem->id,
                    'original_text' => 'Operação estável no período.',
                    'replacement_text' => 'Operação estável e dentro das metas.',
                    'reason' => 'Melhoria de redação.',
                ],
                [
                    'item_id' => $this->disabledItem->id,
                    'original_text' => 'Sistema desabilitado.',
                    'replacement_text' => 'Tentativa em sistema desabilitado.',
                    'reason' => 'Inválido.',
                ],
            ]];
        }
    });

    $this->actingAs($this->admin)
        ->postJson(route('reports.suggestions.store', [$this->team, $report]), [
            'mode' => 'normal',
            'instruction' => 'Faça o comentário das considerações do relatório',
        ])->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('suggestions.0.system_id', $this->system->id);

    expect(ReportSuggestion::query()->where('report_item_id', $disabledItem->id)->exists())->toBeFalse()
        ->and(ReportSuggestion::query()->where('report_item_id', $enabledItem->id)->exists())->toBeTrue();
});

test('consolidated report agent creates and accepts suggestion on previously empty comment', function () {
    $report = createConsolidatedReport($this);

    $emptyItem = ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => true,
        'comment' => null,
    ]);

    app()->instance(GeminiService::class, new class($emptyItem) extends GeminiService
    {
        public function __construct(private $emptyItem) {}

        public function generateJson(string $prompt, array $options = []): ?array
        {
            return ['suggestions' => [
                [
                    'item_id' => $this->emptyItem->id,
                    'original_text' => '',
                    'replacement_text' => 'Novo comentário gerado pela IA para sistema ativo.',
                    'reason' => 'Geração inicial de considerações.',
                ],
            ]];
        }
    });

    $this->actingAs($this->admin)
        ->postJson(route('reports.suggestions.store', [$this->team, $report]), [
            'mode' => 'normal',
            'instruction' => 'Faça o comentário das considerações do relatório',
        ])->assertOk()
        ->assertJsonPath('count', 1);

    $suggestion = ReportSuggestion::query()->where('report_item_id', $emptyItem->id)->firstOrFail();
    expect($suggestion->original_text)->toBe('');

    $this->actingAs($this->admin)
        ->patchJson(route('reports.suggestions.update', [$this->team, $report, $suggestion]), [
            'decision' => 'accept',
        ])->assertOk()->assertJsonPath('status', 'accepted');

    expect($emptyItem->refresh()->comment)->toContain('Novo comentário gerado pela IA para sistema ativo.');
});

test('consolidated report agent responds to questions about its capabilities', function () {
    $report = createConsolidatedReport($this);

    app()->instance(GeminiService::class, new class extends GeminiService
    {
        public function __construct() {}

        public function generateJson(string $prompt, array $options = []): ?array
        {
            return [
                'message' => 'Olá! Como agente de relatórios, posso elaborar comentários para sistemas habilitados, melhorar textos existentes, realizar correção ortográfica e analisar parâmetros.',
                'suggestions' => [],
                'tool_calls' => [],
            ];
        }
    });

    $this->actingAs($this->admin)
        ->postJson(route('reports.suggestions.store', [$this->team, $report]), [
            'mode' => 'normal',
            'instruction' => 'Quais suas funções?',
        ])->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('message', fn ($msg) => str_contains((string) $msg, 'elaborar comentários'));
});

test('consolidated report agent executes tools across any system and records activities', function () {
    $report = createConsolidatedReport($this);
    $item = ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => true,
        'comment' => '<p>Comentário da água bruta.</p>',
    ]);

    app()->instance(GeminiService::class, new class extends GeminiService
    {
        private int $calls = 0;

        public function __construct() {}

        public function generateJson(string $prompt, array $options = []): ?array
        {
            $this->calls++;
            if ($this->calls === 1) {
                return [
                    'message' => 'Vou pesquisar as métricas.',
                    'tool_calls' => [
                        [
                            'name' => 'get_operational_metrics',
                            'arguments' => [
                                'system_id' => MonitoredSystem::query()->value('id'),
                                'start_date' => '2026-08-01',
                                'end_date' => '2026-08-04',
                            ],
                        ],
                    ],
                ];
            }

            return [
                'message' => 'Analisei as métricas de água bruta e o comportamento está normal.',
                'suggestions' => [],
                'tool_calls' => [],
            ];
        }
    });

    $this->actingAs($this->admin)
        ->postJson(route('reports.suggestions.store', [$this->team, $report]), [
            'mode' => 'normal',
            'instruction' => 'Analise os dados de água bruta',
        ])->assertOk()
        ->assertJsonPath('message', 'Analisei as métricas de água bruta e o comportamento está normal.')
        ->assertJsonPath('activity.0.tool', 'get_operational_metrics')
        ->assertJsonPath('activity.0.label', fn ($label) => str_contains((string) $label, 'Água bruta'));
});

test('administrator can group a system into a primary system and clear comments', function () {
    $report = createConsolidatedReport($this);

    // Create item with comment for otherSystem
    $otherItem = ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->otherSystem->id,
        'date_reference' => '2026-08-04',
        'show_data_results' => true,
        'comment' => '<p>Comentário da água tratada</p>',
    ]);

    $this->actingAs($this->admin)
        ->putJson(route('reports.systems.update', [$this->team, $report]), [
            'system_id' => $this->otherSystem->id,
            'parent_system_id' => $this->system->id,
            'clear_comment' => true,
        ])->assertOk()
        ->assertJsonPath('show_data_results', true)
        ->assertJsonPath('parent_report_item_id', fn ($id) => $id !== null);

    expect($otherItem->refresh()->comment)->toBeNull()
        ->and($otherItem->parentReportItem->monitored_system_id)->toBe($this->system->id);
});

test('grouped system reorders parameters matching primary names first and non-matching at the end', function () {
    $report = createConsolidatedReport($this);

    // Primary system parameter 1: Turbidez (sort_order 1, from beforeEach)
    // Primary system parameter 2: pH (sort_order 2)
    $paramPh = Parameter::query()->create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'pH',
        'unit' => '',
        'decimals' => 2,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Child system parameter 1: Condutividade (sort_order 1) - matches primary param
    // Child system parameter 2: Pressão (sort_order 2) - new param
    $paramChildCond = Parameter::query()->create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->otherSystem->id,
        'name' => 'Turbidez', // matches $this->parameter (name 'Turbidez')
        'unit' => 'NTU',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $paramChildPress = Parameter::query()->create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->otherSystem->id,
        'name' => 'Pressão',
        'unit' => 'bar',
        'decimals' => 1,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Group otherSystem under system
    $this->actingAs($this->admin)
        ->putJson(route('reports.systems.update', [$this->team, $report]), [
            'system_id' => $this->otherSystem->id,
            'parent_system_id' => $this->system->id,
        ])->assertOk();

    $response = $this->actingAs($this->admin)
        ->getJson(route('reports.systems.data', [$this->team, $report, $this->system]))
        ->assertOk();

    $parameters = $response->json('parameters');
    $parameterNames = array_column($parameters, 'name');

    // Expected order: Turbidez (primary), Turbidez (child), pH (primary), Pressão (child)
    expect($parameterNames[0])->toBe('Turbidez')
        ->and($parameterNames[1])->toBe('Turbidez')
        ->and($parameterNames[2])->toBe('pH')
        ->and($parameterNames[3])->toBe('Pressão');
});

test('administrator can ungroup a grouped system', function () {
    $report = createConsolidatedReport($this);

    $this->actingAs($this->admin)
        ->putJson(route('reports.systems.update', [$this->team, $report]), [
            'system_id' => $this->otherSystem->id,
            'parent_system_id' => $this->system->id,
        ])->assertOk();

    $otherItem = ReportItem::query()->where('monitored_system_id', $this->otherSystem->id)->first();
    expect($otherItem->parent_report_item_id)->not->toBeNull();

    $this->actingAs($this->admin)
        ->putJson(route('reports.systems.update', [$this->team, $report]), [
            'system_id' => $this->otherSystem->id,
            'parent_system_id' => null,
        ])->assertOk();

    expect($otherItem->refresh()->parent_report_item_id)->toBeNull();
});

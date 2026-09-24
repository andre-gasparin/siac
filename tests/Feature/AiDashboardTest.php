<?php

use App\Features\Dashboards\Services\DashboardAiService;
use App\Infrastructure\AI\GeminiService;
use App\Models\Dashboard;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create(['name' => 'Klabin']);
    $this->team->members()->attach($this->user, ['role' => 'admin']);
    $this->user->update(['current_team_id' => $this->team->id]);

    $this->system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Caldeira 1',
        'is_active' => true,
    ]);

    $this->parameter1 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'Vazão de Vapor',
        'unit' => 't/h',
        'is_active' => true,
    ]);

    $this->parameter2 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'Pressão do Tanque',
        'unit' => 'bar',
        'is_active' => true,
    ]);
});

test('user can request detailed ai dashboard suggestions with populated chart series and non-overlapping grid layout', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('dashboards.ai-suggest', ['current_team' => $this->team->slug]), [
            'objective' => 'Análise de caldeiras e vazão de água',
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'suggestions' => [
                '*' => [
                    'id',
                    'title',
                    'description',
                    'component_count_label',
                    'components' => [
                        '*' => [
                            'type',
                            'title',
                            'grid_config' => ['x', 'y', 'w', 'h'],
                            'settings',
                        ],
                    ],
                ],
            ],
        ]);

    $suggestions = $response->json('suggestions');
    expect($suggestions)->toHaveCount(3);

    $firstSug = $suggestions[0];
    $components = $firstSug['components'];

    $chartComponents = array_filter($components, fn ($c) => $c['type'] === 'chart');
    expect($chartComponents)->not->toBeEmpty();

    foreach ($chartComponents as $chart) {
        $series = $chart['settings']['series'] ?? [];
        expect($series)->not->toBeEmpty();
        foreach ($series as $s) {
            expect($s)->toHaveKeys(['parameter_id', 'label', 'chart_type', 'color', 'axis_position']);
            expect($s['parameter_id'])->not->toBeNull();
            expect($s['label'])->toMatch('/^(Vazão de Vapor \(t\/h\)|Pressão do Tanque \(bar\))$/');
            expect($s['color'])->toMatch('/^#[0-9A-Fa-f]{6}$/');
        }
    }

    $indicatorComponents = array_filter($components, fn ($c) => $c['type'] === 'indicator');
    $textComponents = array_filter($components, fn ($c) => $c['type'] === 'text');
    expect(count($indicatorComponents))->toBeLessThanOrEqual(4);
    expect(count($textComponents))->toBeLessThanOrEqual(2);
    foreach ($indicatorComponents as $ind) {
        expect($ind['settings']['parameter_id'])->not->toBeNull();
    }
});

test('dashboard ai service uses generic gemini service', function () {
    $gemini = app(GeminiService::class);
    $dashboardAi = app(DashboardAiService::class);

    expect($gemini)->toBeInstanceOf(GeminiService::class);
    expect($dashboardAi)->toBeInstanceOf(DashboardAiService::class);

    $suggestions = $dashboardAi->generateDashboardSuggestions($this->team, 'Manutenção Preventiva');
    expect($suggestions)->toHaveCount(3);
    expect(count($suggestions[0]['components']))->toBeGreaterThanOrEqual(1);
});

test('dashboard suggestion formatter enforces component limits and descriptive chart legends', function () {
    $components = [];

    for ($index = 0; $index < 6; $index++) {
        $components[] = [
            'type' => 'indicator',
            'title' => "Indicador {$index}",
            'parameter_id' => $this->parameter1->id,
        ];
    }

    $components[] = [
        'type' => 'chart',
        'title' => 'Tendência operacional',
        'series' => [[
            'parameter_id' => $this->parameter1->id,
            'label' => 'Série 1',
        ]],
    ];

    for ($index = 0; $index < 4; $index++) {
        $components[] = [
            'type' => 'text',
            'title' => "Texto {$index}",
            'content' => 'Contexto operacional.',
        ];
    }

    $suggestions = app(DashboardAiService::class)->formatSuggestions([
        [
            'title' => 'Proposta limitada',
            'components' => $components,
        ],
    ], Parameter::query()->whereKey([$this->parameter1->id, $this->parameter2->id])->get());

    $formattedComponents = $suggestions[0]['components'];

    expect(array_filter($formattedComponents, fn ($component) => $component['type'] === 'indicator'))
        ->toHaveCount(4)
        ->and(array_filter($formattedComponents, fn ($component) => $component['type'] === 'text'))
        ->toHaveCount(2)
        ->and($formattedComponents[4]['settings']['series'][0]['label'])
        ->toBe('Vazão de Vapor (t/h)');
});

test('user can create dashboard from ai proposal with components', function () {
    $components = [
        [
            'type' => 'indicator',
            'grid_config' => ['x' => 20, 'y' => 20, 'w' => 340, 'h' => 200],
            'settings' => [
                'title' => 'Vazão de Vapor',
                'parameter_id' => $this->parameter1->id,
                'aggregation' => 'avg',
            ],
        ],
        [
            'type' => 'chart',
            'grid_config' => ['x' => 20, 'y' => 240, 'w' => 560, 'h' => 360],
            'settings' => [
                'title' => 'Histórico Caldeira 1',
                'series' => [
                    [
                        'parameter_id' => $this->parameter1->id,
                        'label' => 'Vazão de Vapor',
                        'chart_type' => 'line',
                        'color' => '#3B82F6',
                        'stroke_width' => 2,
                        'independent_axis' => false,
                        'axis_position' => 'left',
                    ],
                    [
                        'parameter_id' => $this->parameter2->id,
                        'label' => 'Pressão do Tanque',
                        'chart_type' => 'line',
                        'color' => '#EF4444',
                        'stroke_width' => 2,
                        'independent_axis' => true,
                        'axis_position' => 'right',
                    ],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->postJson(route('dashboards.ai-create', ['current_team' => $this->team->slug]), [
            'title' => 'Análise Detalhada Caldeiras IA',
            'is_public' => false,
            'components' => $components,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['redirect_url']);

    $this->assertDatabaseHas('dashboards', [
        'title' => 'Análise Detalhada Caldeiras IA',
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
    ]);

    $dashboard = Dashboard::where('title', 'Análise Detalhada Caldeiras IA')->first();
    expect($dashboard->components)->toHaveCount(2);

    $chartComp = $dashboard->components->firstWhere('type', 'chart');
    expect($chartComp->settings['series'])->toHaveCount(2);
    expect($chartComp->settings['series'][1]['axis_position'])->toBe('right');
});

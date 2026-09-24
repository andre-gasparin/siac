<?php

use App\Models\ChartTemplate;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach([$this->user->id], ['role' => 'admin']);

    $this->system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Sistema de Produção',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->parameter1 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'pH Entrata',
        'unit' => 'pH',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->parameter2 = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'Vazão Principal',
        'unit' => 'm3/h',
        'sort_order' => 2,
        'is_active' => true,
    ]);
});

test('user can list team chart templates', function () {
    $template = ChartTemplate::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'name' => 'Gráfico Favorito Teste',
        'is_favorite' => true,
        'options' => ['height' => 340],
    ]);

    $template->series()->create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'parameter_id' => $this->parameter1->id,
        'axis' => 1,
        'sort_order' => 0,
        'label' => 'pH Entrata',
        'color' => '#2563EB',
        'options' => [
            'chart_type' => 'line',
            'stroke_width' => 2,
            'show_points' => true,
            'show_values' => false,
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('reports.chart-templates.index', ['current_team' => $this->team]));

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $template->id)
        ->assertJsonPath('0.name', 'Gráfico Favorito Teste')
        ->assertJsonPath('0.is_favorite', true)
        ->assertJsonPath('0.series.0.parameter_id', $this->parameter1->id)
        ->assertJsonPath('0.series.0.system_name', 'Sistema de Produção');
});

test('user can store a new favorite chart template', function () {
    $payload = [
        'name' => 'Novo Gráfico Favorito',
        'is_favorite' => true,
        'options' => ['height' => 320],
        'series' => [
            [
                'parameter_id' => $this->parameter1->id,
                'label' => 'pH Entrata',
                'chart_type' => 'line',
                'color' => '#2563EB',
                'stroke_width' => 2,
                'axis_position' => 'left',
                'show_points' => true,
                'show_values' => false,
            ],
            [
                'parameter_id' => $this->parameter2->id,
                'label' => 'Vazão Principal',
                'chart_type' => 'bar',
                'color' => '#E11D48',
                'stroke_width' => 3,
                'axis_position' => 'right',
                'show_points' => false,
                'show_values' => true,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->postJson(route('reports.chart-templates.store', ['current_team' => $this->team]), $payload);

    $response->assertCreated()
        ->assertJsonPath('name', 'Novo Gráfico Favorito')
        ->assertJsonPath('is_favorite', true)
        ->assertJsonCount(2, 'series');

    $this->assertDatabaseHas('chart_templates', [
        'team_id' => $this->team->id,
        'name' => 'Novo Gráfico Favorito',
        'is_favorite' => true,
    ]);

    $this->assertDatabaseCount('chart_series', 2);
});

test('user can update an existing favorite chart template', function () {
    $template = ChartTemplate::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'name' => 'Gráfico Antigo',
        'is_favorite' => true,
    ]);

    $template->series()->create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'parameter_id' => $this->parameter1->id,
        'axis' => 1,
        'sort_order' => 0,
        'label' => 'pH Entrata',
        'color' => '#2563EB',
        'options' => ['chart_type' => 'line'],
    ]);

    $payload = [
        'name' => 'Gráfico Atualizado',
        'is_favorite' => true,
        'options' => ['height' => 400],
        'series' => [
            [
                'parameter_id' => $this->parameter2->id,
                'label' => 'Vazão Atualizada',
                'chart_type' => 'area',
                'color' => '#059669',
                'stroke_width' => 4,
                'axis_position' => 'left',
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->putJson(route('reports.chart-templates.update', [
            'current_team' => $this->team,
            'chart_template' => $template->id,
        ]), $payload);

    $response->assertOk()
        ->assertJsonPath('name', 'Gráfico Atualizado')
        ->assertJsonCount(1, 'series')
        ->assertJsonPath('series.0.label', 'Vazão Atualizada');

    $this->assertDatabaseHas('chart_templates', [
        'id' => $template->id,
        'name' => 'Gráfico Atualizado',
    ]);
});

test('user can delete a favorite chart template', function () {
    $template = ChartTemplate::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'name' => 'Gráfico Para Deletar',
        'is_favorite' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson(route('reports.chart-templates.destroy', [
            'current_team' => $this->team,
            'chart_template' => $template->id,
        ]));

    $response->assertNoContent();

    $this->assertDatabaseMissing('chart_templates', [
        'id' => $template->id,
    ]);
});

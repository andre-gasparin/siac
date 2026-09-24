<?php

use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->member = User::factory()->create(['is_admin' => false]);
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->admin, ['role' => 'admin']);
    $this->team->members()->attach($this->member, ['role' => 'member']);
    $this->member->update(['current_team_id' => $this->team->id]);

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

test('user reports index lists only completed reports for team members', function () {
    $draftReport = Report::query()->create([
        'team_id' => $this->team->id,
        'created_by' => $this->admin->id,
        'title' => 'Relatório Rascunho',
        'date_reference' => '2026-08-04',
        'status' => 'draft',
    ]);

    $completedReport = Report::query()->create([
        'team_id' => $this->team->id,
        'created_by' => $this->admin->id,
        'title' => 'Relatório Finalizado',
        'date_reference' => '2026-08-03',
        'status' => 'completed',
        'finished_at' => now(),
    ]);

    $this->actingAs($this->member)
        ->get(route('reports.user.index', $this->team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/UserIndex')
            ->has('reports.data', 1)
            ->where('reports.data.0.id', $completedReport->id));
});

test('user report show renders only enabled systems with daily data and considerations', function () {
    $report = Report::query()->create([
        'team_id' => $this->team->id,
        'created_by' => $this->admin->id,
        'title' => 'Relatório 03/08/2026',
        'date_reference' => '2026-08-03',
        'status' => 'completed',
        'finished_at' => now(),
    ]);

    $item = ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-08-03',
        'show_data_results' => true,
        'comment' => '<p>Consideração da Consucal</p>',
    ]);

    ParameterValue::query()->create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'parameter_id' => $this->parameter->id,
        'measured_at' => '2026-08-03 08:00:00',
        'measured_date' => '2026-08-03',
        'value' => 3.25,
        'source_type' => 'manual',
    ]);

    $this->actingAs($this->member)
        ->get(route('reports.user.show', [$this->team, $report]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/UserShow')
            ->has('systems', 1)
            ->where('systems.0.name', 'Água bruta')
            ->where('systems.0.item.comment', '<p>Consideração da Consucal</p>')
            ->has('dataBySystem.'.$this->system->id.'.rows', 1));
});

test('user cannot access draft reports via user show endpoint', function () {
    $draftReport = Report::query()->create([
        'team_id' => $this->team->id,
        'created_by' => $this->admin->id,
        'title' => 'Relatório Rascunho',
        'date_reference' => '2026-08-04',
        'status' => 'draft',
    ]);

    $this->actingAs($this->member)
        ->get(route('reports.user.show', [$this->team, $draftReport]))
        ->assertNotFound();
});

test('user can submit comments on report item in user show view', function () {
    $report = Report::query()->create([
        'team_id' => $this->team->id,
        'created_by' => $this->admin->id,
        'title' => 'Relatório 03/08/2026',
        'date_reference' => '2026-08-03',
        'status' => 'completed',
        'finished_at' => now(),
    ]);

    $item = ReportItem::query()->create([
        'team_id' => $this->team->id,
        'report_id' => $report->id,
        'monitored_system_id' => $this->system->id,
        'date_reference' => '2026-08-03',
        'show_data_results' => true,
    ]);

    $this->actingAs($this->member)
        ->post(route('reports.user.comments.store', [$this->team, $report]), [
            'report_item_id' => $item->id,
            'body' => 'Verificado pela equipe da fábrica.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('report_comments', [
        'report_item_id' => $item->id,
        'user_id' => $this->member->id,
        'body' => 'Verificado pela equipe da fábrica.',
    ]);
});

test('user can fetch parameter chart data on user chart endpoint', function () {
    $report = Report::query()->create([
        'team_id' => $this->team->id,
        'created_by' => $this->admin->id,
        'title' => 'Relatório 03/08/2026',
        'date_reference' => '2026-08-03',
        'status' => 'completed',
        'finished_at' => now(),
    ]);

    ParameterValue::query()->create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'parameter_id' => $this->parameter->id,
        'measured_at' => '2026-08-03 08:00:00',
        'measured_date' => '2026-08-03',
        'value' => 2.5,
        'source_type' => 'manual',
    ]);

    $this->actingAs($this->member)
        ->getJson(route('reports.user.systems.chart', [
            $this->team,
            $report,
            $this->system,
            'parameter_id' => $this->parameter->id,
            'type' => 'line',
        ]))
        ->assertOk()
        ->assertJsonPath('series.0.chart_type', 'line')
        ->assertJsonPath('series.0.data.0.value', 2.5);
});

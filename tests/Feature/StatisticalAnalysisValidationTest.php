<?php

use App\Features\StatisticalAnalysis\Services\StatisticalAnalysisBuilder;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => 'admin']);
    $this->user->update(['current_team_id' => $this->team->id]);

    $this->system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Caldeira',
        'is_active' => true,
    ]);
    $this->parameter = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $this->system->id,
        'name' => 'Temperatura',
        'decimals' => 2,
        'is_active' => true,
    ]);

    foreach (['2026-09-08' => 9, '2026-09-09' => 11, '2026-09-10' => 12] as $date => $value) {
        ParameterValue::create([
            'team_id' => $this->team->id,
            'monitored_system_id' => $this->system->id,
            'parameter_id' => $this->parameter->id,
            'measured_at' => $date.' 10:00:00',
            'measured_date' => $date,
            'value' => $value,
        ]);
    }

    $this->filters = [
        'system_id' => $this->system->id,
        'parameter_ids' => [$this->parameter->id],
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-10',
    ];
});

test('statistical analysis applies defaults to omitted or empty optional filters', function (array $optionalFilters) {
    $this->actingAs($this->user)
        ->getJson(route('statistical-analysis.data', [
            'current_team' => $this->team->slug,
            ...$this->filters,
            ...$optionalFilters,
        ]))
        ->assertOk()
        ->assertJsonPath('config.lookback_value', 30)
        ->assertJsonPath('config.lookback_unit', 'days')
        ->assertJsonPath('config.frequency', 'raw')
        ->assertJsonPath('config.ewma_lambda', 0.2)
        ->assertJsonPath('config.cusum_k', 0.5)
        ->assertJsonPath("results.{$this->parameter->id}.summary.total_analyzed", 1);
})->with([
    'omitted' => [[]],
    'only frequency' => [['frequency' => 'raw']],
    'only lookback unit' => [['lookback_unit' => 'days']],
    'empty nullable fields' => [[
        'lookback_value' => '',
        'lookback_unit' => '',
        'frequency' => '',
        'ewma_lambda' => '',
        'cusum_k' => '',
    ]],
]);

test('statistical analysis builder accepts omitted optional filters directly', function () {
    $result = app(StatisticalAnalysisBuilder::class)->build($this->team, $this->filters);

    expect($result['config']['lookback_unit'])->toBe('days')
        ->and($result['config']['frequency'])->toBe('raw');
});

test('statistical analysis rejects malformed manual overrides', function (mixed $override, string $field) {
    $this->actingAs($this->user)
        ->getJson(route('statistical-analysis.data', [
            'current_team' => $this->team->slug,
            ...$this->filters,
            'manual_overrides' => [$this->parameter->id => $override],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors("manual_overrides.{$this->parameter->id}{$field}");
})->with([
    'scalar override' => ['invalid', ''],
    'invalid mean' => [['mean' => 'invalid'], '.mean'],
    'invalid deviation' => [['std_dev' => 'invalid'], '.std_dev'],
    'zero deviation' => [['std_dev' => 0], '.std_dev'],
    'negative deviation' => [['std_dev' => -1], '.std_dev'],
    'non-finite mean' => [['mean' => '1e309'], '.mean'],
    'non-finite deviation' => [['std_dev' => '1e309'], '.std_dev'],
    'unexpected field' => [['unexpected' => 1], ''],
]);

test('statistical analysis normalizes numeric query strings and applies valid manual overrides', function () {
    $this->actingAs($this->user)
        ->getJson(route('statistical-analysis.data', [
            'current_team' => $this->team->slug,
            ...$this->filters,
            'manual_overrides' => [$this->parameter->id => ['mean' => '0', 'std_dev' => '2.5']],
        ]))
        ->assertOk()
        ->assertJsonPath("results.{$this->parameter->id}.baseline.effective_mu_0", 0)
        ->assertJsonPath("results.{$this->parameter->id}.baseline.effective_sigma_0", 2.5)
        ->assertJsonPath("results.{$this->parameter->id}.baseline.is_overridden_mean", true)
        ->assertJsonPath("results.{$this->parameter->id}.baseline.is_overridden_std_dev", true);
});

test('statistical analysis treats empty overrides as automatic baseline values', function () {
    $this->actingAs($this->user)
        ->getJson(route('statistical-analysis.data', [
            'current_team' => $this->team->slug,
            ...$this->filters,
            'manual_overrides' => [$this->parameter->id => ['mean' => '', 'std_dev' => '']],
        ]))
        ->assertOk()
        ->assertJsonPath("results.{$this->parameter->id}.baseline.effective_mu_0", 10)
        ->assertJsonPath("results.{$this->parameter->id}.baseline.is_overridden_mean", false)
        ->assertJsonPath("results.{$this->parameter->id}.baseline.is_overridden_std_dev", false);
});

test('statistical analysis preserves valid factors at the boundaries of the HTTP contract', function () {
    $this->actingAs($this->user)
        ->getJson(route('statistical-analysis.data', [
            'current_team' => $this->team->slug,
            ...$this->filters,
            'num_alert_levels' => '1',
            'ewma_levels' => ['0.1'],
            'cusum_k' => '5',
            'cusum_levels' => ['0.5'],
            'manual_overrides' => [$this->parameter->id => ['mean' => '10', 'std_dev' => '1']],
        ]))
        ->assertOk()
        ->assertJsonPath('config.ewma_levels', [0.1])
        ->assertJsonPath('config.cusum_k', 5)
        ->assertJsonPath('config.cusum_levels', [0.5])
        ->assertJsonPath("results.{$this->parameter->id}.rows.0.ewma.levels.0.multiplier", 0.1)
        ->assertJsonPath("results.{$this->parameter->id}.rows.0.cusum.k_value", 5)
        ->assertJsonPath("results.{$this->parameter->id}.rows.0.cusum.levels.0.threshold", 0.5);
});

test('statistical analysis preserves small positive manual deviations in the calculations', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('statistical-analysis.data', [
            'current_team' => $this->team->slug,
            ...$this->filters,
            'manual_overrides' => [$this->parameter->id => ['std_dev' => '1e-8']],
        ]))
        ->assertOk();

    expect($response->json("results.{$this->parameter->id}.rows.0.ewma.memorial.sigma_0"))
        ->toEqualWithDelta(1e-8, 1e-12);
});

test('statistical analysis rejects non-finite level multipliers', function (string $field) {
    $this->actingAs($this->user)
        ->getJson(route('statistical-analysis.data', [
            'current_team' => $this->team->slug,
            ...$this->filters,
            $field => ['1e309'],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors("{$field}.0");
})->with(['ewma_levels', 'cusum_levels']);

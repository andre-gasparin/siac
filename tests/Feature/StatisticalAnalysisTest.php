<?php

use App\Features\StatisticalAnalysis\Services\CusumCalculator;
use App\Features\StatisticalAnalysis\Services\EwmaCalculator;
use App\Models\MonitoredSystem;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => 'admin']);
    $this->user->update(['current_team_id' => $this->team->id]);
});

test('user can view statistical analysis page for current team', function () {
    $response = $this->actingAs($this->user)
        ->get(route('statistical-analysis.index', ['current_team' => $this->team->slug]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('StatisticalAnalysis/Index')
            ->has('systems')
            ->has('currentTeam')
            ->has('defaultStartDate')
            ->has('defaultEndDate')
            ->has('defaultLookbackValue')
            ->has('defaultLookbackUnit')
            ->has('defaultFrequency')
        );
});

test('statistical analysis data endpoint calculates ewma and cusum with baseline lookback', function () {
    $system = MonitoredSystem::create([
        'team_id' => $this->team->id,
        'name' => 'Caldeira Principal',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $parameter = Parameter::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'name' => 'pH Caldeira',
        'code' => 'PH_CALD',
        'unit' => 'pH',
        'decimals' => 2,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Baseline readings (previous 5 days) around 7.0
    for ($i = 5; $i >= 1; $i--) {
        $dt = Carbon::parse('2026-09-10 10:00:00')->subDays($i);
        ParameterValue::create([
            'team_id' => $this->team->id,
            'monitored_system_id' => $system->id,
            'parameter_id' => $parameter->id,
            'measured_at' => $dt,
            'measured_date' => $dt->toDateString(),
            'value' => 7.00 + ($i % 2 === 0 ? 0.05 : -0.05),
        ]);
    }

    // Analysis period readings (2026-09-10 to 2026-09-12)
    // 2 normal points, then 1 spiked point that triggers alerts
    $dt1 = Carbon::parse('2026-09-10 10:00:00');
    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => $dt1,
        'measured_date' => $dt1->toDateString(),
        'value' => 7.02,
    ]);

    $dt2 = Carbon::parse('2026-09-11 10:00:00');
    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => $dt2,
        'measured_date' => $dt2->toDateString(),
        'value' => 7.04,
    ]);

    $dt3 = Carbon::parse('2026-09-12 10:00:00');
    ParameterValue::create([
        'team_id' => $this->team->id,
        'monitored_system_id' => $system->id,
        'parameter_id' => $parameter->id,
        'measured_at' => $dt3,
        'measured_date' => $dt3->toDateString(),
        'value' => 8.50, // Massive spike
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('statistical-analysis.data', [
            'current_team' => $this->team->slug,
            'system_id' => $system->id,
            'parameter_ids' => [$parameter->id],
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'lookback_value' => 10,
            'lookback_unit' => 'days',
            'frequency' => 'raw',
            'num_alert_levels' => 2,
            'ewma_lambda' => 0.20,
            'ewma_levels' => [2.0, 3.0],
            'cusum_k' => 0.50,
            'cusum_levels' => [4.0, 5.0],
        ]));

    $response->assertOk()
        ->assertJsonStructure([
            'config',
            'parameters',
            'results' => [
                $parameter->id => [
                    'parameter_id',
                    'parameter_name',
                    'baseline' => [
                        'count',
                        'calculated_mu_0',
                        'calculated_sigma_0',
                        'effective_mu_0',
                        'effective_sigma_0',
                    ],
                    'summary' => [
                        'total_analyzed',
                        'ewma',
                        'cusum',
                    ],
                    'chart_data',
                    'rows' => [
                        '*' => [
                            'index',
                            'timestamp',
                            'raw_value',
                            'ewma' => [
                                'z_value',
                                'sigma_z',
                                'alert_level',
                                'closest_limit',
                                'memorial',
                            ],
                            'cusum' => [
                                'c_pos',
                                'c_neg',
                                'alert_level',
                                'closest_threshold',
                                'memorial',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

    $data = $response->json("results.{$parameter->id}");
    expect($data['baseline']['count'])->toBe(5);
    expect($data['summary']['total_analyzed'])->toBe(3);

    // The third point was 8.50, so EWMA and CUSUM should flag an alert
    $lastRow = $data['rows'][2];
    expect($lastRow['raw_value'])->toBe(8.5);
    expect($lastRow['ewma']['alert_level'])->toBeGreaterThan(0);
    expect($lastRow['cusum']['alert_level'])->toBeGreaterThan(0);
    expect($lastRow['ewma']['memorial']['formula_str'])->toBe('Z_i = λ · X_i + (1 - λ) · Z_{i-1}');
});

test('ewma calculator unit math computes exact variance and recurrence', function () {
    $calculator = new EwmaCalculator;
    $points = [
        ['timestamp' => '2026-09-01 10:00', 'date' => '01/09/2026', 'time' => '10:00', 'value' => 10.0],
        ['timestamp' => '2026-09-02 10:00', 'date' => '02/09/2026', 'time' => '10:00', 'value' => 12.0],
    ];

    $mu0 = 10.0;
    $sigma0 = 1.0;
    $lambda = 0.2;
    $multipliers = [2.0, 3.0];

    $res = $calculator->calculate($points, $mu0, $sigma0, $lambda, $multipliers);

    expect($res['total_count'])->toBe(2);
    // Point 1: Z_1 = 0.2*10.0 + 0.8*10.0 = 10.0
    expect($res['series'][0]['z_value'])->toEqualWithDelta(10.0, 0.001);
    expect($res['series'][0]['sigma_z'])->toEqualWithDelta(0.2, 0.000001);
    // Point 2: Z_2 = 0.2*12.0 + 0.8*10.0 = 2.4 + 8.0 = 10.4
    expect($res['series'][1]['z_value'])->toEqualWithDelta(10.4, 0.001);
    expect($res['series'][1]['sigma_z'])->toEqualWithDelta(0.2561249695, 0.000001);
});

test('cusum calculator unit math computes bilateral sums and alerts', function () {
    $calculator = new CusumCalculator;
    $points = [
        ['timestamp' => '2026-09-01 10:00', 'date' => '01/09/2026', 'time' => '10:00', 'value' => 10.0],
        ['timestamp' => '2026-09-02 10:00', 'date' => '02/09/2026', 'time' => '10:00', 'value' => 12.0],
    ];

    $mu0 = 10.0;
    $sigma0 = 1.0;
    $kFactor = 0.5; // K = 0.5
    $decisionIntervals = [4.0, 5.0];

    $res = $calculator->calculate($points, $mu0, $sigma0, $kFactor, $decisionIntervals);

    // Point 1: X=10, mu0=10, K=0.5 -> C_1^+ = max(0, 10 - 10.5 + 0) = 0
    expect($res['series'][0]['c_pos'])->toEqualWithDelta(0.0, 0.001);
    // Point 2: X=12, mu0=10, K=0.5 -> C_2^+ = max(0, 12 - 10.5 + 0) = 1.5
    expect($res['series'][1]['c_pos'])->toEqualWithDelta(1.5, 0.001);
});

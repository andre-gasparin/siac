<?php

use App\Features\StatisticalAnalysis\Services\EwmaCalculator;

test('rolling EWMA uses the sample deviation and the existing recurrence with one control level', function () {
    $result = (new EwmaCalculator)->evaluate(30, [10.0, 12.0, 14.0]);

    expect($result['mean'])->toEqual(12.0)
        ->and($result['sigma'])->toEqual(2.0)
        ->and($result['previous_ewma'])->toEqualWithDelta(12.144, 0.000001)
        ->and($result['ewma'])->toEqualWithDelta(15.7152, 0.000001)
        ->and($result['upper_limit'])->toEqualWithDelta(12 + 6 * sqrt((0.2 / 1.8) * (1 - 0.8 ** 8)), 0.000001)
        ->and($result['is_alert'])->toBeTrue()
        ->and($result['side'])->toBe('upper');
});

test('rolling EWMA limits the baseline to the requested number of previous readings', function () {
    $calculator = new EwmaCalculator;
    $result = $calculator->evaluate(10, [999.0, 10.0, 12.0, 14.0], lookback: 3);
    expect($result)->toBe($calculator->evaluate(10, [10.0, 12.0, 14.0], lookback: 3))
        ->and($result['history_count'])->toBe(3)
        ->and($result['side'])->toBeNull();
});

test('EWMA returns an explicit unavailable result for insufficient history', function (array $history) {
    $result = (new EwmaCalculator)->evaluate(30, $history);
    expect($result['available'])->toBeFalse()->and($result['is_alert'])->toBeFalse();
})->with(['none' => [[]], 'one' => [[10.0]]]);

test('EWMA handles constant baselines and exact equality without division by zero', function () {
    $calculator = new EwmaCalculator;
    expect($calculator->evaluate(10, [10.0, 10.0])['is_alert'])->toBeFalse()
        ->and($calculator->evaluate(11, [10.0, 10.0])['side'])->toBe('upper')
        ->and($calculator->evaluate(9, [10.0, 10.0])['side'])->toBe('lower');
});

test('EWMA rejects invalid service settings', function (float $lambda, float $level, int $lookback) {
    expect(fn () => (new EwmaCalculator)->evaluate(10, [10.0, 11.0], $lambda, $level, $lookback))->toThrow(InvalidArgumentException::class);
})->with([[0.0, 3.0, 20], [1.1, 3.0, 20], [0.2, 0.0, 20], [0.2, 3.0, 1]]);

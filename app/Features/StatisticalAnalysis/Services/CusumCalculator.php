<?php

namespace App\Features\StatisticalAnalysis\Services;

class CusumCalculator
{
    /**
     * @param  list<array{timestamp: string, date: string, time: string, value: float}>  $points
     * @param  list<float>  $decisionIntervals
     * @return array{
     *     series: list<array<string, mixed>>,
     *     in_control_count: int,
     *     alert_counts: array<int, int>,
     *     total_count: int,
     *     in_control_percentage: float
     * }
     */
    public function calculate(
        array $points,
        float $mu0,
        float $sigma0,
        float $kFactor,
        array $decisionIntervals,
        int $warmupOffset = 0,
    ): array {
        $series = [];
        $cPos = 0.0;
        $cNeg = 0.0;
        $k = $kFactor * $sigma0;

        $alertCounts = array_fill_keys(array_keys($decisionIntervals), 0);
        $inControlCount = 0;
        $totalAnalyzed = 0;

        // Compute decision limits H_j for each level
        $levels = [];
        foreach ($decisionIntervals as $levelIdx => $hMultiplier) {
            $levelNum = $levelIdx + 1;
            $hVal = $hMultiplier * $sigma0;
            $levels[$levelNum] = [
                'level' => $levelNum,
                'multiplier' => $hMultiplier,
                'threshold' => $hVal,
            ];
        }

        foreach ($points as $index => $point) {
            $stepIndex = $index + 1;
            $x = $point['value'];
            $prevCPos = $cPos;
            $prevCNeg = $cNeg;

            // Recurrence formulas:
            // C_i^+ = max(0, X_i - (mu0 + K) + C_{i-1}^+)
            // C_i^- = max(0, (mu0 - K) - X_i + C_{i-1}^-)
            $posArg = $x - ($mu0 + $k) + $prevCPos;
            $cPos = max(0.0, $posArg);

            $negArg = ($mu0 - $k) - $x + $prevCNeg;
            $cNeg = max(0.0, $negArg);

            // Determine alert level
            $triggeredLevel = 0;
            $violatedSide = null; // 'pos' | 'neg' | 'both'
            $numLevels = count($decisionIntervals);

            for ($lvl = $numLevels; $lvl >= 1; $lvl--) {
                $thresh = $levels[$lvl]['threshold'];
                $isPosBreach = $cPos > $thresh;
                $isNegBreach = $cNeg > $thresh;

                if ($isPosBreach || $isNegBreach) {
                    $triggeredLevel = $lvl;
                    $violatedSide = ($isPosBreach && $isNegBreach) ? 'both' : ($isPosBreach ? 'pos' : 'neg');
                    break;
                }
            }

            // Calculate closest threshold and margin
            $maxC = max($cPos, $cNeg);
            $closest = $this->calculateClosestThreshold($maxC, $levels, $triggeredLevel, $violatedSide);

            $isWarmup = $index < $warmupOffset;

            if (! $isWarmup) {
                $totalAnalyzed++;
                if ($triggeredLevel === 0) {
                    $inControlCount++;
                } else {
                    $alertCounts[$triggeredLevel - 1] = ($alertCounts[$triggeredLevel - 1] ?? 0) + 1;
                }
            }

            // Substitution strings for memorial
            $subPosStr = sprintf(
                'C_%d^+ = max(0, %.4f - (%.4f + %.4f) + %.4f) = max(0, %.4f) = %.4f',
                $stepIndex,
                $x,
                $mu0,
                $k,
                $prevCPos,
                $posArg,
                $cPos
            );

            $subNegStr = sprintf(
                'C_%d^- = max(0, (%.4f - %.4f) - %.4f + %.4f) = max(0, %.4f) = %.4f',
                $stepIndex,
                $mu0,
                $k,
                $x,
                $prevCNeg,
                $negArg,
                $cNeg
            );

            $series[] = [
                'index' => $stepIndex,
                'is_warmup' => $isWarmup,
                'timestamp' => $point['timestamp'],
                'date' => $point['date'],
                'time' => $point['time'],
                'raw_value' => $x,
                'c_pos' => $cPos,
                'c_neg' => $cNeg,
                'k_value' => $k,
                'levels' => array_values($levels),
                'alert_level' => $triggeredLevel,
                'violated_side' => $violatedSide,
                'closest_threshold' => $closest,
                'memorial' => [
                    'step' => $stepIndex,
                    'k_factor' => $kFactor,
                    'k_value' => $k,
                    'raw_value' => $x,
                    'previous_c_pos' => $prevCPos,
                    'previous_c_neg' => $prevCNeg,
                    'c_pos' => $cPos,
                    'c_neg' => $cNeg,
                    'sigma_0' => $sigma0,
                    'mu_0' => $mu0,
                    'formula_pos_str' => 'C_i^+ = max(0, X_i - (μ_0 + K) + C_{i-1}^+)',
                    'substitution_pos_str' => $subPosStr,
                    'formula_neg_str' => 'C_i^- = max(0, (μ_0 - K) - X_i + C_{i-1}^-)',
                    'substitution_neg_str' => $subNegStr,
                    'levels' => array_values($levels),
                    'alert_level' => $triggeredLevel,
                    'violated_side' => $violatedSide,
                    'closest_threshold' => $closest,
                ],
            ];
        }

        $percentage = $totalAnalyzed > 0 ? round(($inControlCount / $totalAnalyzed) * 100, 1) : 100.0;

        return [
            'series' => $series,
            'in_control_count' => $inControlCount,
            'alert_counts' => $alertCounts,
            'total_count' => $totalAnalyzed,
            'in_control_percentage' => $percentage,
        ];
    }

    /**
     * @param  array<int, array{level: int, multiplier: float, threshold: float}>  $levels
     * @return array{
     *     target_name: string,
     *     target_value: float,
     *     margin: float,
     *     is_exceeded: bool,
     *     percentage_used: float
     * }
     */
    private function calculateClosestThreshold(
        float $maxC,
        array $levels,
        int $triggeredLevel,
        ?string $violatedSide
    ): array {
        if ($levels === []) {
            return [
                'target_name' => 'N/A',
                'target_value' => 0.0,
                'margin' => 0.0,
                'is_exceeded' => false,
                'percentage_used' => 0.0,
            ];
        }

        if ($triggeredLevel === 0) {
            $thresh1 = $levels[1]['threshold'];
            $margin = $thresh1 - $maxC;
            $pct = $thresh1 > 0 ? ($maxC / $thresh1) * 100 : 0.0;

            return [
                'target_name' => 'H1',
                'target_value' => $thresh1,
                'margin' => $margin,
                'is_exceeded' => false,
                'percentage_used' => min(100.0, max(0.0, $pct)),
            ];
        }

        $nextLevel = $triggeredLevel + 1;
        if (isset($levels[$nextLevel])) {
            $threshNext = $levels[$nextLevel]['threshold'];
            $margin = $threshNext - $maxC;

            return [
                'target_name' => 'H'.$nextLevel,
                'target_value' => $threshNext,
                'margin' => $margin,
                'is_exceeded' => false,
                'percentage_used' => 100.0,
            ];
        }

        // Top level exceeded
        $threshCurr = $levels[$triggeredLevel]['threshold'];
        $exceededBy = $maxC - $threshCurr;

        return [
            'target_name' => 'H'.$triggeredLevel,
            'target_value' => $threshCurr,
            'margin' => -$exceededBy,
            'is_exceeded' => true,
            'percentage_used' => 100.0 + ($exceededBy > 0 ? 10.0 : 0.0),
        ];
    }
}

<?php

namespace App\Features\StatisticalAnalysis\Services;

class EwmaCalculator
{
    /**
     * @param  list<array{timestamp: string, date: string, time: string, value: float}>  $points
     * @param  list<float>  $multipliers
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
        float $lambda,
        array $multipliers,
        int $warmupOffset = 0,
    ): array {
        $series = [];
        $currentZ = $mu0;
        $alertCounts = array_fill_keys(array_keys($multipliers), 0);
        $inControlCount = 0;
        $totalAnalyzed = 0;

        foreach ($points as $index => $point) {
            $stepIndex = $index + 1; // 1-based index for exact variance
            $x = $point['value'];
            $prevZ = $currentZ;

            // Recurrence: Z_i = \lambda * X_i + (1 - \lambda) * Z_{i-1}
            $currentZ = ($lambda * $x) + ((1.0 - $lambda) * $prevZ);

            // Time-varying exact standard deviation of EWMA
            $varianceFactor = ($lambda / (2.0 - $lambda)) * (1.0 - pow(1.0 - $lambda, 2 * $stepIndex));
            $sigmaZ = $sigma0 * sqrt(max(0.0, $varianceFactor));

            // Compute control limits for each level
            $levels = [];
            foreach ($multipliers as $levelIdx => $lMultiplier) {
                $levelNum = $levelIdx + 1;
                $ucl = $mu0 + ($lMultiplier * $sigmaZ);
                $lcl = $mu0 - ($lMultiplier * $sigmaZ);
                $levels[$levelNum] = [
                    'level' => $levelNum,
                    'multiplier' => $lMultiplier,
                    'ucl' => $ucl,
                    'lcl' => $lcl,
                ];
            }

            // Determine alert level
            $triggeredLevel = 0;
            $violatedSide = null; // 'upper' | 'lower' | null
            $numLevels = count($multipliers);

            for ($lvl = $numLevels; $lvl >= 1; $lvl--) {
                $ucl = $levels[$lvl]['ucl'];
                $lcl = $levels[$lvl]['lcl'];

                if ($currentZ > $ucl) {
                    $triggeredLevel = $lvl;
                    $violatedSide = 'upper';
                    break;
                } elseif ($currentZ < $lcl) {
                    $triggeredLevel = $lvl;
                    $violatedSide = 'lower';
                    break;
                }
            }

            // Calculate closest limit and margin
            $closest = $this->calculateClosestLimit($currentZ, $mu0, $levels, $triggeredLevel, $violatedSide);

            $isWarmup = $index < $warmupOffset;

            if (! $isWarmup) {
                $totalAnalyzed++;
                if ($triggeredLevel === 0) {
                    $inControlCount++;
                } else {
                    $alertCounts[$triggeredLevel - 1] = ($alertCounts[$triggeredLevel - 1] ?? 0) + 1;
                }
            }

            // Detailed arithmetic substitution for memorial
            $term1 = $lambda * $x;
            $term2 = (1.0 - $lambda) * $prevZ;
            $substitutionStr = sprintf(
                'Z_%d = (%.4f × %.4f) + ((1 - %.4f) × %.4f) = %.4f + %.4f = %.4f',
                $stepIndex,
                $lambda,
                $x,
                $lambda,
                $prevZ,
                $term1,
                $term2,
                $currentZ
            );

            $series[] = [
                'index' => $stepIndex,
                'is_warmup' => $isWarmup,
                'timestamp' => $point['timestamp'],
                'date' => $point['date'],
                'time' => $point['time'],
                'raw_value' => $x,
                'z_value' => $currentZ,
                'sigma_z' => $sigmaZ,
                'levels' => array_values($levels),
                'alert_level' => $triggeredLevel,
                'violated_side' => $violatedSide,
                'closest_limit' => $closest,
                'memorial' => [
                    'step' => $stepIndex,
                    'lambda' => $lambda,
                    'raw_value' => $x,
                    'previous_z' => $prevZ,
                    'z_value' => $currentZ,
                    'sigma_0' => $sigma0,
                    'mu_0' => $mu0,
                    'sigma_z' => $sigmaZ,
                    'formula_str' => 'Z_i = λ · X_i + (1 - λ) · Z_{i-1}',
                    'substitution_str' => $substitutionStr,
                    'sigma_formula_str' => 'σ_{Z_i} = σ_0 · √[ (λ / (2 - λ)) · (1 - (1 - λ)^{2i}) ]',
                    'levels' => array_values($levels),
                    'alert_level' => $triggeredLevel,
                    'violated_side' => $violatedSide,
                    'closest_limit' => $closest,
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
     * @param  array<int, array{level: int, multiplier: float, ucl: float, lcl: float}>  $levels
     * @return array{
     *     target_name: string,
     *     target_value: float,
     *     margin: float,
     *     is_exceeded: bool,
     *     percentage_used: float
     * }
     */
    private function calculateClosestLimit(
        float $currentZ,
        float $mu0,
        array $levels,
        int $triggeredLevel,
        ?string $violatedSide
    ): array {
        if ($levels === []) {
            return [
                'target_name' => 'N/A',
                'target_value' => $mu0,
                'margin' => 0.0,
                'is_exceeded' => false,
                'percentage_used' => 0.0,
            ];
        }

        if ($triggeredLevel === 0) {
            // Under control: target is Level 1 limit (UCL1 if >= mu0, LCL1 if < mu0)
            $level1 = $levels[1];
            if ($currentZ >= $mu0) {
                $targetVal = $level1['ucl'];
                $margin = $targetVal - $currentZ;
                $range = $targetVal - $mu0;
                $pct = $range > 0 ? (($currentZ - $mu0) / $range) * 100 : 0.0;

                return [
                    'target_name' => 'UCL 1',
                    'target_value' => $targetVal,
                    'margin' => $margin,
                    'is_exceeded' => false,
                    'percentage_used' => min(100.0, max(0.0, $pct)),
                ];
            } else {
                $targetVal = $level1['lcl'];
                $margin = $currentZ - $targetVal;
                $range = $mu0 - $targetVal;
                $pct = $range > 0 ? (($mu0 - $currentZ) / $range) * 100 : 0.0;

                return [
                    'target_name' => 'LCL 1',
                    'target_value' => $targetVal,
                    'margin' => $margin,
                    'is_exceeded' => false,
                    'percentage_used' => min(100.0, max(0.0, $pct)),
                ];
            }
        }

        // Already in alert: next target is triggeredLevel + 1 or breached limit
        $nextLevel = $triggeredLevel + 1;
        if (isset($levels[$nextLevel])) {
            $isUpper = $violatedSide === 'upper';
            $targetVal = $isUpper ? $levels[$nextLevel]['ucl'] : $levels[$nextLevel]['lcl'];
            $margin = $isUpper ? ($targetVal - $currentZ) : ($currentZ - $targetVal);

            return [
                'target_name' => ($isUpper ? 'UCL ' : 'LCL ').$nextLevel,
                'target_value' => $targetVal,
                'margin' => $margin,
                'is_exceeded' => false,
                'percentage_used' => 100.0,
            ];
        }

        // Top level exceeded
        $isUpper = $violatedSide === 'upper';
        $currLimit = $isUpper ? $levels[$triggeredLevel]['ucl'] : $levels[$triggeredLevel]['lcl'];
        $exceededBy = $isUpper ? ($currentZ - $currLimit) : ($currLimit - $currentZ);

        return [
            'target_name' => ($isUpper ? 'UCL ' : 'LCL ').$triggeredLevel,
            'target_value' => $currLimit,
            'margin' => -$exceededBy,
            'is_exceeded' => true,
            'percentage_used' => 100.0 + ($exceededBy > 0 ? 10.0 : 0.0),
        ];
    }
}

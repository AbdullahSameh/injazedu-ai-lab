<?php

namespace App\Support\Dedup;

/**
 * A pure two-sided Wilson score interval for a binomial proportion, at
 * `config('lab.dedup.eval_ci_confidence')` (0.95 on arrival). This is the
 * arithmetic FR-144 and FR-145 are stated in: FR-144 tests the LOWER
 * bound of precision and recall against the gate; FR-145 tests the UPPER
 * bound of precision for a decisive failure. No database, no dependency.
 */
final class WilsonInterval
{
    /**
     * @return array{lower: float, upper: float}
     */
    public function compute(int $successes, int $n, ?float $confidence = null): array
    {
        if ($n < 0 || $successes < 0 || $successes > $n) {
            throw new \InvalidArgumentException('successes must be between 0 and n, and n must be non-negative');
        }

        if ($n === 0) {
            return ['lower' => 0.0, 'upper' => 1.0];
        }

        $confidence ??= (float) config('lab.dedup.eval_ci_confidence', 0.95);
        $z = self::zScoreFor($confidence);
        $z2 = $z ** 2;

        $phat = $successes / $n;
        $denominator = 1 + $z2 / $n;
        $center = $phat + $z2 / (2 * $n);
        $margin = $z * sqrt(($phat * (1 - $phat) / $n) + ($z2 / (4 * $n ** 2)));

        $lower = ($center - $margin) / $denominator;
        $upper = ($center + $margin) / $denominator;

        return [
            'lower' => max(0.0, $lower),
            'upper' => min(1.0, $upper),
        ];
    }

    /**
     * The z-score for a two-sided interval at the given confidence level —
     * the (1 + confidence)/2 quantile of the standard normal distribution,
     * via Acklam's rational approximation to the inverse normal CDF
     * (accurate to ~1.15e-9, a standard public-domain algorithm — not an
     * external statistics dependency).
     */
    private static function zScoreFor(float $confidence): float
    {
        return self::inverseNormalCdf(1 - (1 - $confidence) / 2);
    }

    private static function inverseNormalCdf(float $p): float
    {
        if ($p <= 0.0 || $p >= 1.0) {
            throw new \InvalidArgumentException('p must be strictly between 0 and 1');
        }

        // Acklam's algorithm coefficients.
        $a = [-3.969683028665376e+01, 2.209460984245205e+02, -2.759285104469687e+02,
            1.383577518672690e+02, -3.066479806614716e+01, 2.506628277459239e+00];
        $b = [-5.447609879822406e+01, 1.615858368580409e+02, -1.556989798598866e+02,
            6.680131188771972e+01, -1.328068155288572e+01];
        $c = [-7.784894002430293e-03, -3.223964580411365e-01, -2.400758277161838e+00,
            -2.549732539343734e+00, 4.374664141464968e+00, 2.938163982698783e+00];
        $d = [7.784695709041462e-03, 3.224671290700398e-01, 2.445134137142996e+00,
            3.754408661907416e+00];

        $pLow = 0.02425;
        $pHigh = 1 - $pLow;

        if ($p < $pLow) {
            $q = sqrt(-2 * log($p));

            return (((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5])
                / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1));
        }

        if ($p <= $pHigh) {
            $q = $p - 0.5;
            $r = $q * $q;

            return (((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q)
                / (((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1));
        }

        $q = sqrt(-2 * log(1 - $p));

        return -(((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5])
            / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1));
    }
}

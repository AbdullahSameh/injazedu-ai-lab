<?php

namespace Tests\Unit\Dedup;

use App\Support\Dedup\WilsonInterval;
use Tests\TestCase;

/**
 * FR-144, FR-145. Hand-computed reference values below — 10/10 and 0/10 at
 * 95% confidence are the standard textbook Wilson score interval examples
 * (the two are symmetric complements of each other under p <-> 1-p, which
 * is itself a check on the arithmetic).
 */
class WilsonIntervalTest extends TestCase
{
    public function test_matches_hand_computed_values_for_ten_out_of_ten_at_95_percent(): void
    {
        $interval = (new WilsonInterval)->compute(successes: 10, n: 10, confidence: 0.95);

        $this->assertEqualsWithDelta(0.7225, $interval['lower'], 0.001);
        $this->assertEqualsWithDelta(1.0, $interval['upper'], 0.0001);
    }

    public function test_matches_hand_computed_values_for_zero_out_of_ten_at_95_percent(): void
    {
        $interval = (new WilsonInterval)->compute(successes: 0, n: 10, confidence: 0.95);

        $this->assertEqualsWithDelta(0.0, $interval['lower'], 0.0001);
        $this->assertEqualsWithDelta(0.2775, $interval['upper'], 0.001);
    }

    public function test_matches_hand_computed_values_for_ninety_out_of_hundred_at_95_percent(): void
    {
        $interval = (new WilsonInterval)->compute(successes: 90, n: 100, confidence: 0.95);

        $this->assertEqualsWithDelta(0.8257, $interval['lower'], 0.001);
        $this->assertEqualsWithDelta(0.9448, $interval['upper'], 0.001);
    }

    public function test_is_symmetric_under_p_and_one_minus_p(): void
    {
        $wilson = new WilsonInterval;

        $successes = $wilson->compute(successes: 10, n: 10, confidence: 0.95);
        $failures = $wilson->compute(successes: 0, n: 10, confidence: 0.95);

        $this->assertEqualsWithDelta(1 - $successes['lower'], $failures['upper'], 0.0001);
        $this->assertEqualsWithDelta(1 - $successes['upper'], $failures['lower'], 0.0001);
    }

    public function test_reads_confidence_from_config_when_not_passed_explicitly(): void
    {
        config(['lab.dedup.eval_ci_confidence' => 0.95]);

        $withConfig = (new WilsonInterval)->compute(successes: 10, n: 10);
        $explicit = (new WilsonInterval)->compute(successes: 10, n: 10, confidence: 0.95);

        $this->assertSame($explicit, $withConfig);
    }

    public function test_zero_n_returns_the_widest_possible_interval(): void
    {
        $interval = (new WilsonInterval)->compute(successes: 0, n: 0);

        $this->assertSame(0.0, $interval['lower']);
        $this->assertSame(1.0, $interval['upper']);
    }

    public function test_bounds_never_exceed_the_zero_to_one_range(): void
    {
        $wilson = new WilsonInterval;

        foreach ([[0, 5], [5, 5], [1, 1000], [999, 1000]] as [$successes, $n]) {
            $interval = $wilson->compute($successes, $n, 0.95);

            $this->assertGreaterThanOrEqual(0.0, $interval['lower']);
            $this->assertLessThanOrEqual(1.0, $interval['upper']);
            $this->assertLessThanOrEqual($interval['upper'], $interval['lower']);
        }
    }
}

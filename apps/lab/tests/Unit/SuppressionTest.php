<?php

namespace Tests\Unit;

use App\Support\Suppression;
use Tests\TestCase;

/**
 * FR-052: `n < 10` publishes nothing, `n < 30` publishes partially,
 * `n >= 30` publishes fully — and only a fully published count is
 * clickable (there is nothing exact to link a hidden or bucketed count
 * through to).
 */
class SuppressionTest extends TestCase
{
    public function test_below_ten_is_hidden_and_not_linkable(): void
    {
        foreach ([0, 1, 9] as $n) {
            $this->assertSame(Suppression::HIDE, Suppression::tier($n));
            $this->assertFalse(Suppression::isLinkable($n));
        }
    }

    public function test_ten_to_twenty_nine_is_partial_and_not_linkable(): void
    {
        foreach ([10, 18, 29] as $n) {
            $this->assertSame(Suppression::PARTIAL, Suppression::tier($n));
            $this->assertFalse(Suppression::isLinkable($n));
        }
    }

    public function test_thirty_and_above_is_full_and_linkable(): void
    {
        foreach ([30, 31, 29142] as $n) {
            $this->assertSame(Suppression::FULL, Suppression::tier($n));
            $this->assertTrue(Suppression::isLinkable($n));
        }
    }

    public function test_display_shows_the_exact_figure_only_once_full(): void
    {
        $this->assertSame(__('console.suppression.hidden'), Suppression::display(5));
        $this->assertSame(__('console.suppression.partial'), Suppression::display(18));
        $this->assertSame(number_format(567), Suppression::display(567));
    }
}

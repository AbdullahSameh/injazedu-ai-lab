<?php

namespace App\Support;

/**
 * The console's small-cell rule (FR-052), applied wherever a group count
 * renders: `n < 10` publishes nothing, `n < 30` publishes a bucket instead
 * of the exact figure, `n >= 30` publishes in full. A bucketed or hidden
 * count carries no drill-through link — there is no exact number to click
 * through to (FR-050 applies only once a count is fully published).
 */
final class Suppression
{
    public const HIDE = 'hide';

    public const PARTIAL = 'partial';

    public const FULL = 'full';

    public static function tier(int $n): string
    {
        return match (true) {
            $n < 10 => self::HIDE,
            $n < 30 => self::PARTIAL,
            default => self::FULL,
        };
    }

    public static function isLinkable(int $n): bool
    {
        return self::tier($n) === self::FULL;
    }

    /** The label to render in place of the exact count. */
    public static function display(int $n): string
    {
        return match (self::tier($n)) {
            self::HIDE => __('console.suppression.hidden'),
            self::PARTIAL => __('console.suppression.partial'),
            self::FULL => number_format($n),
        };
    }
}

<?php

namespace Tests\Unit\Derive;

use App\Support\Derive\OptionIndexDeriver;
use PHPUnit\Framework\TestCase;

class OptionIndexDeriverTest extends TestCase
{
    public function test_orders_by_order_ascending(): void
    {
        $result = (new OptionIndexDeriver)->derive([
            ['id' => 30, 'order' => 3],
            ['id' => 10, 'order' => 1],
            ['id' => 20, 'order' => 2],
        ]);

        $this->assertSame([10, 20, 30], array_column($result, 'id'));
        $this->assertSame([0, 1, 2], array_column($result, 'option_index'));
    }

    /**
     * Query 5's mandatory case: `options.order` defaults to 0 and repeats
     * constantly (notes.md N6). Identical `order` values must resolve by
     * `id` ascending — this is the case that breaks everything silently.
     */
    public function test_order_ties_resolve_by_id_ascending(): void
    {
        $result = (new OptionIndexDeriver)->derive([
            ['id' => 42, 'order' => 0],
            ['id' => 7, 'order' => 0],
            ['id' => 15, 'order' => 0],
        ]);

        $this->assertSame([7, 15, 42], array_column($result, 'id'));
        $this->assertSame([0, 1, 2], array_column($result, 'option_index'));
    }

    public function test_result_is_stable_across_repeated_runs(): void
    {
        $options = [
            ['id' => 5, 'order' => 1],
            ['id' => 2, 'order' => 1],
            ['id' => 9, 'order' => 0],
        ];

        $first = (new OptionIndexDeriver)->derive($options);
        $second = (new OptionIndexDeriver)->derive($options);

        $this->assertSame($first, $second);
    }
}

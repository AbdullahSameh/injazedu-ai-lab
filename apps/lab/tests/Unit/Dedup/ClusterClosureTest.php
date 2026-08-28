<?php

namespace Tests\Unit\Dedup;

use App\Support\Dedup\ClusterClosure;
use PHPUnit\Framework\TestCase;

/** FR-118, FR-119: order-independent, and the size guard reports rather than merges. */
class ClusterClosureTest extends TestCase
{
    public function test_a_b_and_b_c_yield_one_component_of_three(): void
    {
        $result = (new ClusterClosure)->resolve([[1, 2], [2, 3]]);

        $this->assertCount(1, $result['components']);
        $this->assertSame([1, 2, 3], $result['components'][0]['members']);
        $this->assertSame(1, $result['components'][0]['canonical']);
    }

    public function test_shuffling_the_input_pairs_changes_nothing(): void
    {
        $closure = new ClusterClosure;

        $ordered = $closure->resolve([[1, 2], [2, 3], [4, 5]]);
        $shuffled = $closure->resolve([[4, 5], [2, 3], [1, 2]]);

        $normalize = static function (array $result): array {
            $components = array_map(
                static fn (array $c) => $c['members'],
                $result['components']
            );
            sort($components);

            return $components;
        };

        $this->assertSame($normalize($ordered), $normalize($shuffled));
    }

    public function test_canonical_is_always_the_lowest_member(): void
    {
        $result = (new ClusterClosure)->resolve([[30, 10], [10, 20]]);

        $this->assertSame(10, $result['components'][0]['canonical']);
        $this->assertSame([10, 20, 30], $result['components'][0]['members']);
    }

    public function test_disjoint_pairs_form_separate_components(): void
    {
        $result = (new ClusterClosure)->resolve([[1, 2], [10, 11]]);

        $this->assertCount(2, $result['components']);
    }

    public function test_a_component_past_the_size_guard_is_reported_not_returned_as_a_cluster(): void
    {
        // A~B, B~C, C~D chains a 4-member component.
        $result = (new ClusterClosure)->resolve([[1, 2], [2, 3], [3, 4]], sizeGuard: 3);

        $this->assertCount(0, $result['components']);
        $this->assertCount(1, $result['oversized']);
        $this->assertSame(4, $result['oversized'][0]['size']);
        $this->assertSame([1, 2, 3, 4], $result['oversized'][0]['members']);
        $this->assertSame([[1, 2], [2, 3], [3, 4]], $result['oversized'][0]['pairs']);
    }

    public function test_a_component_at_or_under_the_size_guard_is_still_a_normal_cluster(): void
    {
        $result = (new ClusterClosure)->resolve([[1, 2], [2, 3]], sizeGuard: 3);

        $this->assertCount(1, $result['components']);
        $this->assertCount(0, $result['oversized']);
    }

    public function test_no_size_guard_means_no_component_is_ever_flagged(): void
    {
        $result = (new ClusterClosure)->resolve([[1, 2], [2, 3], [3, 4], [4, 5]]);

        $this->assertCount(1, $result['components']);
        $this->assertCount(0, $result['oversized']);
    }
}

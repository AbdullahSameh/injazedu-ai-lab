<?php

namespace App\Support\Dedup;

/**
 * Union-find over a pair list (plan.md decision 4, FR-116 – FR-119). Pure,
 * no database — the pair set is ~114,000 at its upper bound, which is
 * milliseconds and a few MB in PHP, and it makes FR-118's
 * order-independence a property a unit test can prove by shuffling the
 * input rather than something asserted about a recursive CTE.
 *
 * The size guard applies to THIS closure only — never to hash clusters,
 * where a legitimate 538-member group exists (median 3, p99 15). Hash
 * equality is transitive, so that group is a true finding, not a chaining
 * artefact, and this class has no opinion about hash clusters at all.
 */
final class ClusterClosure
{
    /**
     * @param  list<array{0: int, 1: int}>  $pairs
     * @return array{
     *     components: list<array{canonical: int, members: list<int>}>,
     *     oversized: list<array{size: int, members: list<int>, pairs: list<array{0: int, 1: int}>}>
     * }
     */
    public function resolve(array $pairs, ?int $sizeGuard = null): array
    {
        $parent = [];

        $find = function (int $x) use (&$parent, &$find): int {
            if (! array_key_exists($x, $parent)) {
                $parent[$x] = $x;
            }
            if ($parent[$x] !== $x) {
                $parent[$x] = $find($parent[$x]);
            }

            return $parent[$x];
        };

        foreach ($pairs as [$a, $b]) {
            $rootA = $find($a);
            $rootB = $find($b);
            if ($rootA !== $rootB) {
                $parent[$rootB] = $rootA;
            }
        }

        $groups = [];
        foreach (array_keys($parent) as $node) {
            $groups[$find($node)][] = $node;
        }

        $components = [];
        $oversized = [];

        foreach ($groups as $members) {
            sort($members);
            $canonical = $members[0];

            if ($sizeGuard !== null && count($members) > $sizeGuard) {
                $memberSet = array_flip($members);
                $chainingPairs = array_values(array_filter(
                    $pairs,
                    static fn (array $pair): bool => isset($memberSet[$pair[0]]) && isset($memberSet[$pair[1]])
                ));

                $oversized[] = [
                    'size' => count($members),
                    'members' => $members,
                    'pairs' => $chainingPairs,
                ];

                continue;
            }

            $components[] = ['canonical' => $canonical, 'members' => $members];
        }

        return ['components' => $components, 'oversized' => $oversized];
    }
}

<?php

namespace App\Jobs\Dedup;

use App\Models\ImportRun;
use App\Support\Import\ImportRunRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3's deterministic hash pass. Strict full-hash equality is the only
 * automatic cluster key; stem and fuzzy matches merely propose high-band
 * candidate pairs for later review.
 */
final class ClusterExactHashMatches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $importRunId) {}

    /**
     * @return array{exact_clusters: int, formatting_candidates: int, orthographic_candidates: int}
     */
    public function handle(): array
    {
        $run = ImportRun::findOrFail($this->importRunId);
        $recorder = ImportRunRecorder::for($run);
        $questionCount = $this->eligibleQuestions()->count();
        $generatedAt = now();

        $exactClusters = 0;
        $differentMediaCandidates = 0;
        $formattingCandidates = 0;
        $orthographicCandidates = 0;
        $unchanged = 0;
        $desiredCandidates = [];

        $exactProjection = $this->exactProjection();
        [$exactClusters, $existing] = $this->reconcileExactClusters($exactProjection['groups']);
        $unchanged += $existing;

        foreach ($exactProjection['boundary_pairs'] as [$a, $b]) {
            $desiredCandidates[$this->pairKey($a, $b)] = 'exact';
            if ($this->insertCandidate($a, $b, 'exact', $generatedAt)) {
                $differentMediaCandidates++;
            } else {
                $unchanged++;
            }
        }

        foreach ($this->groupedEligibleQuestions('question_text_hash') as $members) {
            foreach ($this->pairsWithDifferent($members, 'question_with_options_hash') as [$a, $b]) {
                $desiredCandidates[$this->pairKey($a, $b)] = 'formatting';
                if ($this->insertCandidate($a, $b, 'formatting', $generatedAt)) {
                    $formattingCandidates++;
                } else {
                    $unchanged++;
                }
            }
        }

        if (config('lab.dedup.fuzzy_fold_enabled', false)) {
            foreach ($this->groupedEligibleQuestions('fuzzy_text_hash') as $members) {
                foreach ($this->pairsWithDifferent($members, 'question_text_hash') as [$a, $b]) {
                    $pairKey = $this->pairKey($a, $b);
                    if (in_array($desiredCandidates[$pairKey] ?? null, ['exact', 'formatting'], true)) {
                        continue;
                    }

                    $desiredCandidates[$pairKey] = 'orthographic';
                    if ($this->insertCandidate($a, $b, 'orthographic', $generatedAt)) {
                        $orthographicCandidates++;
                    } else {
                        $unchanged++;
                    }
                }
            }
        }

        $candidateUpdates = $this->reconcileHashCandidates($desiredCandidates, $generatedAt);
        $unchanged = max(0, $unchanged - $candidateUpdates);
        $inserted = $exactClusters + $differentMediaCandidates + $formattingCandidates + $orthographicCandidates;
        $recorder->recordRead($questionCount);
        $recorder->recordOutcomes($inserted, $candidateUpdates, $unchanged);

        return [
            'exact_clusters' => $exactClusters,
            'formatting_candidates' => $formattingCandidates,
            'orthographic_candidates' => $orthographicCandidates,
        ];
    }

    private function eligibleQuestions(): Builder
    {
        return DB::connection('pgsql')->table('source_question_derived as derived')
            ->join('source_questions as question', 'question.source_id', '=', 'derived.question_source_id')
            ->whereNull('question.source_deleted_at')
            ->where('question.requires_media_review', false)
            ->where('derived.search_text', '<>', '');
    }

    /**
     * Stream one duplicate-key group at a time. SQL supplies the canonical
     * minimum so PHP never needs to materialize the bank merely to find it.
     *
     * @return \Generator<int, Collection<int, object>>
     */
    private function groupedEligibleQuestions(string $groupColumn): \Generator
    {
        if (! in_array($groupColumn, ['question_text_hash', 'question_with_options_hash', 'fuzzy_text_hash'], true)) {
            throw new \InvalidArgumentException("Unsupported hash group column [{$groupColumn}].");
        }

        $query = $this->eligibleQuestions()
            ->when($groupColumn === 'fuzzy_text_hash', static fn (Builder $query): Builder => $query
                ->whereNotNull('derived.fuzzy_text_hash')
                ->where('derived.fuzzy_text_hash', '<>', ''))
            ->select([
                'derived.question_source_id',
                'question.section_source_id',
                'derived.question_text_hash',
                'derived.question_with_options_hash',
                'derived.fuzzy_text_hash',
                'derived.media_fingerprint',
            ])
            ->selectRaw("MIN(derived.question_source_id) OVER (PARTITION BY derived.{$groupColumn}) AS group_canonical_id")
            ->when($groupColumn === 'question_with_options_hash', static fn (Builder $query): Builder => $query
                ->selectRaw('MIN(derived.question_source_id) OVER (PARTITION BY derived.question_with_options_hash, derived.media_fingerprint) AS media_group_canonical_id'))
            ->orderBy("derived.{$groupColumn}")
            ->orderBy('derived.question_source_id');

        $currentHash = null;
        $members = [];

        foreach ($query->lazy(1000) as $question) {
            $hash = (string) $question->{$groupColumn};

            if ($currentHash !== null && $hash !== $currentHash) {
                if (count($members) > 1) {
                    yield new Collection($members);
                }

                $members = [];
            }

            $currentHash = $hash;
            $members[] = $question;
        }

        if (count($members) > 1) {
            yield new Collection($members);
        }
    }

    /**
     * @return array{
     *     groups: array<string, array{canonical_id: int, member_ids: list<int>}>,
     *     boundary_pairs: list<array{0: object, 1: object}>
     * }
     */
    private function exactProjection(): array
    {
        $groups = [];
        $boundaryPairs = [];

        foreach ($this->groupedEligibleQuestions('question_with_options_hash') as $members) {
            foreach ($this->pairsWithDifferent($members, 'media_fingerprint') as $pair) {
                $boundaryPairs[] = $pair;
            }

            foreach ($members->groupBy(static fn (object $member): string => $member->media_fingerprint === null
                ? 'no-media'
                : 'sha256:'.$member->media_fingerprint) as $fingerprintKey => $mediaMembers) {
                if ($mediaMembers->count() < 2) {
                    continue;
                }

                $first = $mediaMembers->first();
                $groups[(string) $first->question_with_options_hash.':'.$fingerprintKey] = [
                    'canonical_id' => (int) $first->media_group_canonical_id,
                    'member_ids' => $mediaMembers->pluck('question_source_id')
                        ->map(static fn (mixed $id): int => (int) $id)
                        ->values()
                        ->all(),
                ];
            }
        }

        return ['groups' => $groups, 'boundary_pairs' => $boundaryPairs];
    }

    /**
     * @param  array<string, 'exact'|'formatting'|'orthographic'>  $desired
     */
    private function reconcileHashCandidates(array $desired, mixed $generatedAt): int
    {
        $updated = 0;

        DB::connection('pgsql')->table('duplicate_candidates')
            ->chunkById(1000, function (Collection $candidates) use ($desired, $generatedAt, &$updated): void {
                $updatesByLevel = ['exact' => [], 'formatting' => [], 'orthographic' => []];
                $clearHashIds = [];
                $deleteIds = [];

                foreach ($candidates as $candidate) {
                    $key = $this->pairKeyFromIds(
                        (int) $candidate->question_a_source_id,
                        (int) $candidate->question_b_source_id,
                    );
                    $desiredLevel = $desired[$key] ?? null;

                    if ($desiredLevel !== null) {
                        if ($candidate->hash_match_level !== $desiredLevel) {
                            $updatesByLevel[$desiredLevel][] = (int) $candidate->id;
                        }

                        continue;
                    }

                    if (! in_array($candidate->hash_match_level, ['exact', 'formatting', 'orthographic'], true)) {
                        continue;
                    }

                    if ($this->hasDownstreamCandidateEvidence($candidate)) {
                        $clearHashIds[] = (int) $candidate->id;
                    } else {
                        $deleteIds[] = (int) $candidate->id;
                    }
                }

                foreach ($updatesByLevel as $level => $ids) {
                    if ($ids === []) {
                        continue;
                    }

                    $updated += DB::connection('pgsql')->table('duplicate_candidates')
                        ->whereIn('id', $ids)
                        ->update([
                            'hash_match_level' => $level,
                            'band' => 'high',
                            'generated_at' => $generatedAt,
                        ]);
                }

                if ($clearHashIds !== []) {
                    $updated += DB::connection('pgsql')->table('duplicate_candidates')
                        ->whereIn('id', $clearHashIds)
                        ->update(['hash_match_level' => null]);
                }

                if ($deleteIds !== []) {
                    DB::connection('pgsql')->table('duplicate_candidates')->whereIn('id', $deleteIds)->delete();
                }
            });

        return $updated;
    }

    private function hasDownstreamCandidateEvidence(object $candidate): bool
    {
        return $candidate->trgm_score !== null
            || $candidate->stem_cosine_sim !== null
            || $candidate->full_cosine_sim !== null
            || $candidate->embedding_config_version_at_generation !== null
            || $candidate->llm_verdict_relation !== null
            || $candidate->llm_same_learning_objective !== null
            || $candidate->llm_same_correct_answer !== null
            || $candidate->llm_confidence !== null
            || $candidate->llm_issues !== null
            || $candidate->llm_recommended_action !== null
            || $candidate->llm_review_required !== null
            || $candidate->llm_prompt_version !== null
            || $candidate->llm_verdict_at !== null
            || (int) $candidate->verdict_attempts > 0
            || $candidate->verdict_last_error !== null
            || (bool) $candidate->verdict_failed;
    }

    private function pairKey(object $a, object $b): string
    {
        return $this->pairKeyFromIds((int) $a->question_source_id, (int) $b->question_source_id);
    }

    private function pairKeyFromIds(int $aId, int $bId): string
    {
        return min($aId, $bId).':'.max($aId, $bId);
    }

    /**
     * @return array{0: int, 1: int} newly-created-clusters, unchanged-operations
     */
    private function reconcileExactClusters(array $desired): array
    {
        $existing = $this->existingHashClusters();
        $assignments = [];
        $assignedClusterIds = [];

        // A reviewed or non-auto cluster is an historical human decision.
        // It may retain an unchanged projection, but never be silently
        // reinterpreted into a different strict-hash group.
        foreach ($existing as $cluster) {
            if (! $cluster['protected']) {
                continue;
            }

            $hash = $this->desiredHashForMembers($desired, $cluster['member_ids']);
            if ($hash === null || isset($assignments[$hash])) {
                throw new \RuntimeException(
                    "Cannot reconcile protected hash cluster #{$cluster['id']}: its current members no longer match a single exact-hash group."
                );
            }

            $assignments[$hash] = $cluster;
            $assignedClusterIds[$cluster['id']] = true;
        }

        foreach ($desired as $hash => $group) {
            if (isset($assignments[$hash])) {
                continue;
            }

            $candidates = array_values(array_filter($existing, static function (array $cluster) use ($group, $assignedClusterIds): bool {
                return ! $cluster['protected']
                    && ! isset($assignedClusterIds[$cluster['id']])
                    && array_intersect($cluster['member_ids'], $group['member_ids']) !== [];
            }));

            usort($candidates, static function (array $left, array $right) use ($group): int {
                $exactMembership = ($left['member_ids'] === $group['member_ids']) <=> ($right['member_ids'] === $group['member_ids']);
                if ($exactMembership !== 0) {
                    return -$exactMembership;
                }

                $overlap = count(array_intersect($right['member_ids'], $group['member_ids'])) <=> count(array_intersect($left['member_ids'], $group['member_ids']));
                if ($overlap !== 0) {
                    return $overlap;
                }

                $sameCanonical = ($left['canonical_id'] === $group['canonical_id']) <=> ($right['canonical_id'] === $group['canonical_id']);
                if ($sameCanonical !== 0) {
                    return -$sameCanonical;
                }

                return $left['id'] <=> $right['id'];
            });

            if ($candidates !== []) {
                $assignments[$hash] = $candidates[0];
                $assignedClusterIds[$candidates[0]['id']] = true;
            }
        }

        return DB::connection('pgsql')->transaction(function () use ($desired, $assignments, $assignedClusterIds, $existing): array {
            $created = 0;
            $unchanged = 0;

            foreach ($desired as $hash => $group) {
                $cluster = $assignments[$hash] ?? null;
                $clusterId = $cluster['id'] ?? null;

                if ($clusterId === null) {
                    $clusterId = DB::connection('pgsql')->table('duplicate_clusters')->insertGetId([
                        'canonical_question_source_id' => $group['canonical_id'],
                        'relation_type' => 'exact_duplicate',
                        'status' => 'auto',
                        'source_layer' => 'hash',
                        'member_count' => count($group['member_ids']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                } else {
                    if (! $cluster['protected']) {
                        DB::connection('pgsql')->table('duplicate_cluster_members')
                            ->where('duplicate_cluster_id', $clusterId)
                            ->whereNotIn('question_source_id', $group['member_ids'])
                            ->delete();
                    }

                    // This is the deterministic projection only; it leaves
                    // status, reviews, and every triage/reviewer field intact.
                    DB::connection('pgsql')->table('duplicate_clusters')->where('id', $clusterId)->update([
                        'canonical_question_source_id' => $group['canonical_id'],
                        'member_count' => count($group['member_ids']),
                        'updated_at' => now(),
                    ]);
                    $unchanged++;
                }

                DB::connection('pgsql')->table('duplicate_cluster_members')->where('duplicate_cluster_id', $clusterId)->update([
                    'is_canonical' => false,
                ]);

                foreach ($group['member_ids'] as $questionId) {
                    DB::connection('pgsql')->table('duplicate_cluster_members')->insertOrIgnore([
                        'duplicate_cluster_id' => $clusterId,
                        'question_source_id' => $questionId,
                        'is_canonical' => $questionId === $group['canonical_id'],
                        'added_at' => now(),
                    ]);
                }

                DB::connection('pgsql')->table('duplicate_cluster_members')
                    ->where('duplicate_cluster_id', $clusterId)
                    ->where('question_source_id', $group['canonical_id'])
                    ->update(['is_canonical' => true]);
            }

            foreach ($existing as $cluster) {
                if (isset($assignedClusterIds[$cluster['id']])) {
                    continue;
                }

                DB::connection('pgsql')->table('duplicate_cluster_members')
                    ->where('duplicate_cluster_id', $cluster['id'])
                    ->delete();
                DB::connection('pgsql')->table('duplicate_clusters')
                    ->where('id', $cluster['id'])
                    ->delete();
            }

            return [$created, $unchanged];
        });
    }

    /**
     * @return list<array{id: int, canonical_id: int, member_ids: list<int>, protected: bool}>
     */
    private function existingHashClusters(): array
    {
        $clusters = [];
        foreach (DB::connection('pgsql')->table('duplicate_clusters as cluster')
            ->leftJoin('duplicate_cluster_members as member', 'member.duplicate_cluster_id', '=', 'cluster.id')
            ->where('cluster.source_layer', 'hash')
            ->orderBy('cluster.id')
            ->orderBy('member.question_source_id')
            ->get(['cluster.id', 'cluster.canonical_question_source_id', 'cluster.relation_type', 'cluster.status', 'member.question_source_id']) as $row) {
            $id = (int) $row->id;
            $clusters[$id] ??= [
                'id' => $id,
                'canonical_id' => (int) $row->canonical_question_source_id,
                'member_ids' => [],
                // A human may legitimately change relation_type while this
                // cluster keeps its historical hash source layer. That
                // decision is protected just like a non-auto status.
                'protected' => $row->status !== 'auto' || $row->relation_type !== 'exact_duplicate',
            ];

            if ($row->question_source_id !== null) {
                $clusters[$id]['member_ids'][] = (int) $row->question_source_id;
            }
        }

        $reviewedIds = DB::connection('pgsql')->table('duplicate_reviews')
            ->whereIn('duplicate_cluster_id', array_keys($clusters))
            ->pluck('duplicate_cluster_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->flip();

        foreach ($clusters as &$cluster) {
            $cluster['protected'] = $cluster['protected'] || isset($reviewedIds[$cluster['id']]);
        }
        unset($cluster);

        return array_values($clusters);
    }

    /** @param array<string, array{canonical_id: int, member_ids: list<int>}> $desired */
    private function desiredHashForMembers(array $desired, array $memberIds): ?string
    {
        foreach ($desired as $hash => $group) {
            if ($group['member_ids'] === $memberIds) {
                return $hash;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, object>  $members
     * @return \Generator<int, array{0: object, 1: object}>
     */
    private function pairsWithDifferent(Collection $members, string $differentColumn): \Generator
    {
        $ordered = $members->sortBy('question_source_id')->values();

        for ($left = 0; $left < $ordered->count() - 1; $left++) {
            for ($right = $left + 1; $right < $ordered->count(); $right++) {
                $a = $ordered[$left];
                $b = $ordered[$right];

                if ($a->{$differentColumn} !== $b->{$differentColumn}) {
                    yield [$a, $b];
                }
            }
        }
    }

    private function insertCandidate(object $a, object $b, string $level, mixed $generatedAt): bool
    {
        $aId = (int) $a->question_source_id;
        $bId = (int) $b->question_source_id;

        return DB::connection('pgsql')->table('duplicate_candidates')->insertOrIgnore([
            'question_a_source_id' => min($aId, $bId),
            'question_b_source_id' => max($aId, $bId),
            'hash_match_level' => $level,
            'same_section' => $a->section_source_id !== null && $a->section_source_id === $b->section_source_id,
            'media_relation' => $this->mediaRelation($a->media_fingerprint, $b->media_fingerprint),
            'band' => 'high',
            'generated_at' => $generatedAt,
        ]) === 1;
    }

    private function mediaRelation(?string $aFingerprint, ?string $bFingerprint): string
    {
        if ($aFingerprint === null && $bFingerprint === null) {
            return 'no_media';
        }

        return $aFingerprint === $bFingerprint ? 'same_media' : 'different_media';
    }
}

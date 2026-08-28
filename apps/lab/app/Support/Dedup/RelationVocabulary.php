<?php

namespace App\Support\Dedup;

/**
 * The two relation vocabularies (data-model.md §9, FR-132 – FR-134).
 * Deliberately NOT one enum: the verdict/human vocabulary carries
 * `not_related` and forbids `probable_duplicate`; the cluster vocabulary is
 * the reverse. `probable_duplicate` means "the threshold said so and
 * nobody looked" — a statement no verdict or human label may make, and
 * `not_related` never gets a cluster, because no cluster is created for
 * unrelated questions.
 *
 * These are the single source of truth for both the database CHECK
 * constraints (2026_08_28_100800_add_relation_vocabulary_check_constraints)
 * and the application-level test — keep the three in sync by hand if this
 * ever changes; a mismatch fails loudly rather than silently.
 */
final class RelationVocabulary
{
    /** @var list<string> duplicate_candidates.llm_verdict_relation and duplicate_eval_pairs.human_relation (FR-132). */
    public const VERDICT_VALUES = [
        'exact_duplicate',
        'formatting_duplicate',
        'semantic_duplicate',
        'same_objective_variant',
        'related_not_duplicate',
        'conflicting_duplicate',
        'not_related',
    ];

    /** @var list<string> duplicate_clusters.relation_type (FR-133). */
    public const CLUSTER_VALUES = [
        'exact_duplicate',
        'formatting_duplicate',
        'semantic_duplicate',
        'same_objective_variant',
        'related_not_duplicate',
        'conflicting_duplicate',
        'probable_duplicate',
    ];

    /** Calibration's positive class (FR-059), taken from §17 literally. */
    public const CALIBRATION_POSITIVE_CLASS = [
        'exact_duplicate',
        'semantic_duplicate',
    ];
}

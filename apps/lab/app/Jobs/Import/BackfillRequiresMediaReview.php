<?php

namespace App\Jobs\Import;

/**
 * `source_questions.requires_media_review` — the second pass FR-034
 * requires, after `source_media` exists (data-model.md §2). `ImportMedia`
 * is the last bank job, so at copy time the media rows are not there yet.
 *
 * The flag means: **this question cannot be judged from its text alone**,
 * because audio or video is attached to it. Two attachment paths count, and
 * both are needed:
 *
 *   - directly, `quiz_files.question_id` → `question_source_id`
 *   - through its section, `quiz_files.section_id` → `section_source_id`,
 *     because a section is a shared stimulus and its audio belongs to every
 *     question under it (§8)
 *
 * The second path is not a completeness nicety. On the fixed 2026-08-07
 * snapshot all four audio rows attach at section level and none at question
 * level (query 10), so a question-level-only rule would flag zero questions
 * and read as "no media review needed anywhere" — false, and invisibly so.
 *
 * Images are excluded on purpose: `has_img` and `is_stem_image_only`
 * already carry the image signal at copy time, and 5,582 of the 5,586 media
 * rows are images — folding them in here would flag a fifth of the bank and
 * say nothing.
 */
final class BackfillRequiresMediaReview extends BackfillJob
{
    protected function mirrorTable(): string
    {
        return 'source_questions';
    }

    protected function statement(): string
    {
        // Two DISTINCT sets hash-joined, rather than an EXISTS correlated
        // per question: 29,142 questions against 5,586 media rows is a seq
        // scan per row the other way round.
        return <<<'SQL'
            UPDATE source_questions AS q
            SET requires_media_review = needed.flag
            FROM (
                SELECT src.id,
                       (qm.question_source_id IS NOT NULL
                        OR sm.section_source_id IS NOT NULL) AS flag
                FROM source_questions src
                LEFT JOIN (
                    SELECT DISTINCT question_source_id
                    FROM source_media
                    WHERE type IN ('audio', 'video')
                      AND question_source_id IS NOT NULL
                ) qm ON qm.question_source_id = src.source_id
                LEFT JOIN (
                    SELECT DISTINCT section_source_id
                    FROM source_media
                    WHERE type IN ('audio', 'video')
                      AND section_source_id IS NOT NULL
                ) sm ON sm.section_source_id = src.section_source_id
            ) AS needed
            WHERE q.id = needed.id
              AND q.requires_media_review IS DISTINCT FROM needed.flag
        SQL;
    }
}

<?php

namespace App\Jobs\Import;

/**
 * `source_sections.questions_count` — the second pass FR-013 requires,
 * after `source_questions` exists (data-model.md §2). `ImportSections` runs
 * before `ImportQuestions` in the mandatory bank order, so at copy time
 * there is nothing to count and the column is left at its default 0.
 *
 * **Live questions only.** Soft-deleted questions are copied (FR-032) but
 * are not part of a section's size: §6 query 8, the number this count is
 * read beside on the console, joins `questions … AND q.deleted_at IS NULL`.
 * Counting them here would make the console disagree with the profiling
 * run over the same snapshot. Exclusion at analysis time is exactly what
 * this is.
 *
 * Soft-deleted *sections* are counted anyway — the section is still a real
 * row with a real size, and hiding it is the console's decision, not this
 * pass's.
 */
final class BackfillQuestionsCount extends BackfillJob
{
    protected function mirrorTable(): string
    {
        return 'source_sections';
    }

    protected function statement(): string
    {
        // LEFT JOIN, not a correlated subquery: a section with no questions
        // must resolve to 0, and COALESCE over an absent group is how that
        // is said without a second statement.
        return <<<'SQL'
            UPDATE source_sections AS s
            SET questions_count = counted.n
            FROM (
                SELECT sec.id, COALESCE(q.n, 0) AS n
                FROM source_sections sec
                LEFT JOIN (
                    SELECT section_source_id, count(*) AS n
                    FROM source_questions
                    WHERE source_deleted_at IS NULL
                      AND section_source_id IS NOT NULL
                    GROUP BY section_source_id
                ) q ON q.section_source_id = sec.source_id
            ) AS counted
            WHERE s.id = counted.id
              AND s.questions_count IS DISTINCT FROM counted.n
        SQL;
    }
}

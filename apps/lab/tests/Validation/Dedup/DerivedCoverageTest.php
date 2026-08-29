<?php

namespace Tests\Validation\Dedup;

use Illuminate\Support\Facades\DB;
use Tests\Validation\TestCase;

/**
 * T043 / FR-020: Phase 3 derives immutable comparison text beside every
 * mirrored question, including the 395 soft-deleted historical rows.
 *
 * This is intentionally a MirrorValidation test: its single query reads the
 * populated Lab mirror only. It never uses RefreshDatabase or changes data.
 */
class DerivedCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.pgsql.database' => 'injazedu_lab']);
        DB::purge('pgsql');
    }

    public function test_every_mirrored_question_has_exactly_one_complete_derived_text_row(): void
    {
        $coverage = DB::connection('pgsql')->selectOne(<<<'SQL'
            SELECT
                count(*) AS question_count,
                COALESCE(sum(derived.derived_row_count), 0) AS derived_row_count,
                count(*) FILTER (
                    WHERE derived.derived_row_count = 1
                      AND derived.search_text IS NOT NULL
                      AND derived.normalizer_version IS NOT NULL
                ) AS complete_question_count
            FROM source_questions AS question
            LEFT JOIN LATERAL (
                SELECT
                    count(*) AS derived_row_count,
                    max(search_text) AS search_text,
                    max(normalizer_version) AS normalizer_version
                FROM source_question_derived
                WHERE question_source_id = question.source_id
            ) AS derived ON true
            SQL);

        $this->assertSame(29_142, (int) $coverage->question_count, 'The fixed mirror question count drifted.');
        $this->assertSame(29_142, (int) $coverage->derived_row_count, 'A question is missing a derived row or has more than one.');
        $this->assertSame(29_142, (int) $coverage->complete_question_count, 'Every derived row must have search_text and normalizer_version.');
    }
}

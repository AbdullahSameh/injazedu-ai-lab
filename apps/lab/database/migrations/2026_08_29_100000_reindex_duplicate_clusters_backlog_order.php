<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * P2-owned. NOT part of the P1 mirror.
     *
     * FR-089 requires the review backlog to be ordered by student impact, so
     * it can never fall back to ordering by `id`. The index created with the
     * table (2026_08_28_100300) was written with Laravel's `$table->index()`,
     * which cannot express a per-column direction and so emitted all three
     * columns ASC. That serves `WHERE status = ? AND priority_tier = ?
     * ORDER BY affected_student_count DESC` via a backward scan, but not the
     * mixed-direction sort the backlog actually issues, where `status` and
     * `priority_tier` are ordered as well as filtered.
     *
     * Rebuilt here with the trailing column DESC. Naming it explicitly also
     * escapes Postgres' 63-character truncation, which had left the old index
     * called `..._affected_student_count_` with a dangling underscore.
     *
     * NULL ordering is left at the Postgres default (DESC implies NULLS
     * FIRST). `affected_student_count` is nullable only in the window before
     * the impact pass populates it, and a query that wants unknowns last must
     * say `DESC NULLS LAST` — which this index would then not serve. If the
     * console needs that form, change both together.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS duplicate_clusters_status_priority_tier_affected_student_count_');

        DB::statement(<<<'SQL'
            CREATE INDEX duplicate_clusters_backlog_order_index
                ON duplicate_clusters (status, priority_tier, affected_student_count DESC)
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS duplicate_clusters_backlog_order_index');

        DB::statement(<<<'SQL'
            CREATE INDEX duplicate_clusters_status_priority_tier_affected_student_count_
                ON duplicate_clusters (status, priority_tier, affected_student_count)
            SQL);
    }
};

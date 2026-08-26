<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `results.user_id` is NULL on 808,776 of 1,136,204 rows (71% —
     * discovered running the real import, undocumented in data-model.md
     * §2 or the source schema doc). 767,138 of those are also
     * soft-deleted; 41,638 are live rows with no linked user. There is no
     * id to hash for these, so `student_ref` stays NULL rather than a
     * fabricated or sentinel value that would falsely correlate unrelated
     * anonymous attempts under one identity. `DeriveAttemptIndex` leaves
     * `attempt_index` NULL for these rows too — "this student's Nth
     * attempt" is undefined without a student.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE source_results ALTER COLUMN student_ref DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE source_results ALTER COLUMN student_ref SET NOT NULL');
    }
};

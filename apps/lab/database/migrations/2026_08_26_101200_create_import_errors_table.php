<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only, scoped by `import_run_id`, never deleted or rewritten
     * (spec clarification, FR-027) — this is the property the console's
     * card design depends on: a no-op re-import logs nothing, which is
     * exactly why the quality cards read the mirror's own columns and
     * never this table.
     *
     * `context` is JSONB and is filled by code written under pressure. A
     * `user_id` in an error payload is a PII leak that no column
     * assertion catches, so hashing happens at read time, before any
     * error path can see the raw value (FR-020).
     */
    public function up(): void
    {
        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_run_id');
            $table->string('source_table');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('severity', 10); // info | warning | error
            $table->string('code', 40); // ImportErrorCode case, Phase 5
            $table->text('message');
            $table->jsonb('context')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('import_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_errors');
    }
};

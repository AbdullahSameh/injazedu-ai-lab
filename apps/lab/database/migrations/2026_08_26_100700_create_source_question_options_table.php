<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors `options` (data-model.md §2, FR-014, FR-017). `source_order`
     * is `options.order` copied as-is — it defaults to 0 and repeats
     * constantly (notes N6); `option_index` is the derived, gap-free
     * ordering (`ORDER BY order ASC, id ASC`, never abbreviated) that the
     * rest of this project actually relies on. A/B/C/D letters do not
     * exist in the source and are never stored — they are synthesized
     * from `option_index` at render time only.
     */
    public function up(): void
    {
        Schema::create('source_question_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_source_id')->nullable();
            $table->string('raw_text'); // options.name
            $table->integer('points')->default(0);
            $table->integer('source_order')->default(0); // options.order, as-is
            $table->integer('option_index'); // derived, stable, never abbreviated
            $table->boolean('is_correct_derived'); // points > 0 — no correctness column exists (§5.1)

            // Common mirror columns (data-model.md §1).
            $table->text('source_system');
            $table->unsignedBigInteger('source_id');
            $table->timestampTz('source_created_at')->nullable();
            $table->timestampTz('source_updated_at')->nullable();
            $table->timestampTz('source_deleted_at')->nullable();
            $table->timestampTz('imported_at');
            $table->unsignedBigInteger('import_run_id');
            $table->char('payload_hash', 64);

            $table->unique(['source_system', 'source_id']);
            $table->index('question_source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_question_options');
    }
};

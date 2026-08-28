<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2-owned. NOT part of the P1 mirror. Populated only
     * `WHERE has_stimulus = true` (FR-021) — measured 2026-08-28: 0 of
     * 3,316 `source_sections` rows qualify on this snapshot, so this table
     * stays empty and the coverage test asserts that rather than assuming
     * it (data-model.md §3).
     *
     * No embedding column — adding one before a passage exists is
     * speculation; it is one migration to add later.
     */
    public function up(): void
    {
        Schema::create('source_section_derived', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_source_id');

            $table->text('clean_text');
            $table->text('search_text');
            $table->char('stimulus_text_hash', 64);
            $table->text('normalizer_version');

            $table->timestampTz('text_computed_at')->nullable();

            $table->unique('section_source_id');
            $table->index('stimulus_text_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_section_derived');
    }
};

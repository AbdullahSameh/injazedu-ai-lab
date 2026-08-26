<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Mirrors `chapters` — title and order only (data-model.md §2). */
    public function up(): void
    {
        Schema::create('source_chapters', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('sort_order')->nullable(); // chapters.sorte_order (sic)
            $table->unsignedBigInteger('course_source_id');

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_chapters');
    }
};

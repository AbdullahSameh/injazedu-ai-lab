<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors `lectures` — title and order only (data-model.md §2).
     *
     * Not copied: `zoom_start_url`, `zoom_join_url`, `meeting_id`,
     * `passcode`, `meeting_type`, `vimeo_id`, `bunny_id`, `youtube_id`,
     * `upload_status`, `upload_error`, `host`, `live`, `book`,
     * `start_time`, `start_date_hijri`, `duration` — some are credentials,
     * none is about a question.
     */
    public function up(): void
    {
        Schema::create('source_lectures', function (Blueprint $table) {
            $table->id();
            $table->string('topic');
            $table->integer('sort_order')->nullable(); // lectures.sorte_order (sic)
            $table->unsignedBigInteger('chapter_source_id');

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
        Schema::dropIfExists('source_lectures');
    }
};

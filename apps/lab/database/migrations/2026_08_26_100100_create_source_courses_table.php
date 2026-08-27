<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors `courses` — metadata only (data-model.md §2, FR-012).
     *
     * Not copied: `price`, `discount`, `description` (NOT NULL in the
     * source — notes N7, omitting it is a decision, not an oversight),
     * `course_conditions`, `meta_title`, `meta_description`, `image`,
     * `poster`, `schedule`, `intro`, `live_days`, `live_time`,
     * `expire_duration`, `start_date_hijri`, `sorte_order`.
     */
    public function up(): void
    {
        Schema::create('source_courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('category_source_id');
            $table->boolean('status')->default(true);
            $table->date('start_date');
            $table->date('exam_date')->nullable();
            $table->string('telegram_channel')->nullable();
            $table->string('telegram_group')->nullable();
            $table->string('telegram_private')->nullable();

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
        Schema::dropIfExists('source_courses');
    }
};

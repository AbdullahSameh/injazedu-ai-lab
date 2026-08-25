<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The probe table (data-model.md §5). One row, always id 1 — a fixed id
     * keeps re-running the probe idempotent, so the check never accumulates
     * rows (notes.md N4). No column here may hold personal data (FR-024).
     */
    public function up(): void
    {
        Schema::create('lab_job_probes', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('ran_at')->nullable();
            $table->unsignedInteger('worker_pid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_job_probes');
    }
};

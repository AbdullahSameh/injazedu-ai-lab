<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row, always id 1. The fixed id makes the probe idempotent, and no
     * column in this Lab-owned table may hold personal data (FR-017, FR-025).
     */
    public function up(): void
    {
        Schema::create('lab_vector_probes', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->vector('embedding', 768);
            $table->timestamp('written_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_vector_probes');
    }
};

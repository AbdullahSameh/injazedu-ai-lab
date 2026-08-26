<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mirrors `sections` — where the shared stimulus lives (§8, data-model.md §2). */
class SourceSection extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'quiz_source_id', 'name', 'order',
        'stimulus_raw', 'stimulus_length', 'has_stimulus', 'is_long_stimulus', 'questions_count',
        'source_system', 'source_id',
        'source_created_at', 'source_updated_at', 'source_deleted_at',
        'imported_at', 'import_run_id', 'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'has_stimulus' => 'boolean',
            'is_long_stimulus' => 'boolean',
            'source_created_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'source_deleted_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(SourceQuiz::class, 'quiz_source_id', 'source_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SourceQuestion::class, 'section_source_id', 'source_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(SourceMedia::class, 'section_source_id', 'source_id');
    }
}

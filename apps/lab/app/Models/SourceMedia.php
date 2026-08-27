<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mirrors `quiz_files` (data-model.md §2, notes N3, FR-035).
 * `source_deleted_at` is structurally always NULL — `quiz_files` has no
 * soft delete in the source.
 */
class SourceMedia extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'type', 'path', 'section_source_id', 'question_source_id', 'attach_level', 'path_unverified',
        'source_system', 'source_id',
        'source_created_at', 'source_updated_at', 'source_deleted_at',
        'imported_at', 'import_run_id', 'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'path_unverified' => 'boolean',
            'source_created_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'source_deleted_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SourceSection::class, 'section_source_id', 'source_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SourceQuestion::class, 'question_source_id', 'source_id');
    }
}

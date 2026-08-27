<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mirrors `options` (data-model.md §2, FR-014, FR-017). A/B/C/D letters do
 * not exist in the source and are never stored — synthesize them from
 * `option_index` at render time only.
 */
class SourceQuestionOption extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'question_source_id', 'raw_text', 'points', 'source_order', 'option_index', 'is_correct_derived',
        'source_system', 'source_id',
        'source_created_at', 'source_updated_at', 'source_deleted_at',
        'imported_at', 'import_run_id', 'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_correct_derived' => 'boolean',
            'source_created_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'source_deleted_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SourceQuestion::class, 'question_source_id', 'source_id');
    }
}

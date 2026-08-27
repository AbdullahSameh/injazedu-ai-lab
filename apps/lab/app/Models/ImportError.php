<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only, scoped by `import_run_id`, never deleted or rewritten
 * (FR-027). `context` must never carry a `user_id` — hashing happens at
 * read time, before any error path can see the raw value.
 */
class ImportError extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'import_run_id', 'source_table', 'source_id', 'severity', 'code', 'message', 'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }
}

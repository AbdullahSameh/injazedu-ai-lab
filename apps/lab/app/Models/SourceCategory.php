<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mirrors `categories` (data-model.md §2). No `*_source_id` column carries a DB-level FK — orphans and cycles are logged, never enforced away. */
class SourceCategory extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'name', 'slug', 'sort_order', 'parent_source_id', 'image',
        'source_system', 'source_id',
        'source_created_at', 'source_updated_at', 'source_deleted_at',
        'imported_at', 'import_run_id', 'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'source_created_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'source_deleted_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_source_id', 'source_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_source_id', 'source_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(SourceCourse::class, 'category_source_id', 'source_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(SourceQuiz::class, 'category_source_id', 'source_id');
    }
}

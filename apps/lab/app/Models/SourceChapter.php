<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mirrors `chapters` — title and order only (data-model.md §2). */
class SourceChapter extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'title', 'sort_order', 'course_source_id',
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

    public function course(): BelongsTo
    {
        return $this->belongsTo(SourceCourse::class, 'course_source_id', 'source_id');
    }

    public function lectures(): HasMany
    {
        return $this->hasMany(SourceLecture::class, 'chapter_source_id', 'source_id');
    }
}

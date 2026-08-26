<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mirrors `quizzes` (data-model.md §2). `course_source_id` NULL means a general/open quiz. */
class SourceQuiz extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'name', 'slug', 'description', 'sort_order', 'duration', 'hint',
        'course_source_id', 'category_source_id', 'lecture_source_id',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(SourceCategory::class, 'category_source_id', 'source_id');
    }

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(SourceLecture::class, 'lecture_source_id', 'source_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SourceSection::class, 'quiz_source_id', 'source_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(SourceResult::class, 'quiz_source_id', 'source_id');
    }
}

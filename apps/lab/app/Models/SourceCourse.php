<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mirrors `courses` — metadata only (data-model.md §2, FR-012). */
class SourceCourse extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'name', 'slug', 'category_source_id', 'status', 'start_date', 'exam_date',
        'telegram_channel', 'telegram_group', 'telegram_private',
        'source_system', 'source_id',
        'source_created_at', 'source_updated_at', 'source_deleted_at',
        'imported_at', 'import_run_id', 'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'start_date' => 'date',
            'exam_date' => 'date',
            'source_created_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'source_deleted_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SourceCategory::class, 'category_source_id', 'source_id');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(SourceChapter::class, 'course_source_id', 'source_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(SourceQuiz::class, 'course_source_id', 'source_id');
    }
}

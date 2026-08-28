<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P2-owned (data-model.md §6). `question_source_id` joins the mirror through
 * `source_id`, never the surrogate `id`.
 */
class DuplicateClusterMember extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'duplicate_cluster_id', 'question_source_id', 'is_canonical', 'added_at',
    ];

    protected function casts(): array
    {
        return [
            'is_canonical' => 'boolean',
            'added_at' => 'datetime',
        ];
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(DuplicateCluster::class, 'duplicate_cluster_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SourceQuestion::class, 'question_source_id', 'source_id');
    }
}

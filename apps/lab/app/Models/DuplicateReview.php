<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P2-owned (data-model.md §7). The one irreproducible artefact in the Lab —
 * append-only. `reviewer_id` -> `users.id` is the Lab's own operator
 * account, never a Production/InjazEdu identity.
 */
class DuplicateReview extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'duplicate_cluster_id', 'decision', 'reviewer_id', 'reviewed_at',
        'previous_status', 'new_status', 'previous_relation_type', 'new_relation_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(DuplicateCluster::class, 'duplicate_cluster_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P2-owned (data-model.md §3). Populated only where `has_stimulus = true`
 * — empty on this snapshot, and the coverage test asserts that rather than
 * assuming it.
 */
class SourceSectionDerived extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'source_section_derived'; // Eloquent's guesser wrongly pluralizes "derived" to "deriveds"

    public $timestamps = false;

    protected $fillable = [
        'section_source_id',
        'clean_text', 'search_text', 'stimulus_text_hash', 'normalizer_version',
        'text_computed_at',
    ];

    protected function casts(): array
    {
        return [
            'text_computed_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SourceSection::class, 'section_source_id', 'source_id');
    }
}

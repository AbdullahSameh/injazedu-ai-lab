<?php

namespace App\Jobs\Import\Bank;

use App\Support\Derive\PayloadHasher;

/**
 * `quiz_files` → `source_media` — both attachment levels (FR-035,
 * data-model.md §2, notes N3). `path_unverified` is always true —
 * Production storage is unreachable locally. `quiz_files` has no
 * `deleted_at` column at all, so it is never in `selectColumns()`; the base
 * class's `$row->deleted_at ?? null` reads that as NULL, which is exactly
 * the structurally-always-NULL `source_deleted_at` this table needs.
 */
final class ImportMedia extends BankImportJob
{
    protected function sourceTable(): string
    {
        return 'quiz_files';
    }

    protected function mirrorTable(): string
    {
        return 'source_media';
    }

    protected function selectColumns(): array
    {
        return ['id', 'type', 'path', 'section_id', 'question_id', 'created_at', 'updated_at'];
    }

    protected function mapAttributes(object $row): array
    {
        $content = [
            'type' => $row->type,
            'path' => $row->path,
        ];

        $derived = [
            'section_source_id' => $row->section_id,
            'question_source_id' => $row->question_id,
            'attach_level' => $row->section_id !== null ? 'section' : 'question',
            'path_unverified' => true,
        ];

        return $content + $derived + ['payload_hash' => (new PayloadHasher)->hash($content)];
    }
}

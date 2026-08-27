<?php

namespace App\Support\Import;

use App\Models\ImportRun;

/**
 * `import_runs.resume_cursor` — (table, last confirmed `source_id`) pairs
 * (FR-025, data-model.md §2). `confirm()` persists immediately: call it once
 * per confirmed batch, never per row, and only after that batch's writes
 * have actually committed — never before.
 *
 * **This cursor is id-based, and that is only valid against a frozen
 * snapshot.** `WHERE id > ?` sees rows appended after the last confirmed id;
 * it is structurally blind to *edits* of rows already copied, because it
 * never looks at them again. Against the fixed 2026-08-07 snapshot nothing
 * changes, so this is correct and cheap.
 *
 * A live source would need a watermark on `(updated_at, id)` — ordered and
 * compared as a pair, since `updated_at` alone is not unique and a
 * same-timestamp batch can straddle a boundary. Recorded here rather than
 * built: P1 has no live source, and guessing at the shape of one is how
 * unused machinery gets written (ADR-022, decision 10).
 */
final class ResumeCursor
{
    public function __construct(private readonly ImportRun $run) {}

    public function confirm(string $table, int $lastConfirmedSourceId): void
    {
        $cursor = $this->run->resume_cursor ?? [];
        $cursor[$table] = $lastConfirmedSourceId;

        $this->run->forceFill(['resume_cursor' => $cursor])->save();
    }

    public function lastConfirmed(string $table): ?int
    {
        return $this->run->resume_cursor[$table] ?? null;
    }
}

<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Guard 3 of the source read-only enforcement (FR-003, FR-006, data-model.md §2).
 *
 * Thrown by App\Support\SourceReader. Since P0 §3.2 (2026-08-23) there are two
 * refusal kinds — refused for reading, refused for copying — and the message
 * names the table and points at the list that governs the act (SC-004).
 */
class SourceTableNotAllowed extends InvalidArgumentException
{
    public static function forReading(string $table): self
    {
        return new self(
            "Table [{$table}] is not readable from the source: it is on neither allowlist ".
            '(config/lab.php → source_tables ∪ profile_tables).'
        );
    }

    public static function forCopying(string $table): self
    {
        return new self(
            "Table [{$table}] may not be copied into the Lab: copying is governed by source_tables alone ".
            '(config/lab.php). Profile tables are read as counts and never stored (P0 §3.2).'
        );
    }
}

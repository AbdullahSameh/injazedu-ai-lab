<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Guard 3 of the source read-only enforcement (FR-003, FR-006, data-model.md §2).
 *
 * Thrown by App\Support\SourceReader when asked to read a table outside
 * config('lab.source_tables'). The message names the offending table so a
 * failed allowlist assertion identifies exactly what was refused (SC-004).
 */
class SourceTableNotAllowed extends InvalidArgumentException
{
    public static function forTable(string $table): self
    {
        return new self(
            "Table [{$table}] is not on the source allowlist (config/lab.php → source_tables)."
        );
    }
}

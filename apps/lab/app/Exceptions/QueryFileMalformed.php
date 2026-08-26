<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by App\Support\Profiling\QueryFile when a `sql/profiling/*.sql` file
 * has no parseable `-- Tables read :` / `-- Allowlist :` header. A missing or
 * unparseable header is a hard failure, never a default-to-empty (FR-002,
 * notes.md N5) — the declaration is what `assertReadable()` checks before any
 * file executes, so a file without one cannot be trusted to name its tables.
 */
class QueryFileMalformed extends RuntimeException
{
    public static function missingHeader(string $filename, string $field): self
    {
        return new self(
            "Query file [{$filename}] has no parseable \"{$field}\" header line."
        );
    }
}

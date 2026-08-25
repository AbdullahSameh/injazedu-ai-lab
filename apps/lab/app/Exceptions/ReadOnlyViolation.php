<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Guard 2 of the source read-only enforcement (FR-002, data-model.md §1).
 *
 * Thrown by the query listener registered in AppServiceProvider when a
 * statement on the `injazedu` connection does not begin with SELECT, SHOW,
 * DESCRIBE, or EXPLAIN. MySQL itself enforces nothing — root can write —
 * so this layer must block alone (SC-003).
 */
class ReadOnlyViolation extends RuntimeException
{
    public static function forStatement(string $query): self
    {
        $preview = mb_substr(preg_replace('/\s+/', ' ', trim($query)), 0, 120);

        return new self(
            "The injazedu connection is read-only; refused statement: {$preview}"
        );
    }
}

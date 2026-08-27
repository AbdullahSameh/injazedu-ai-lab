<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * One anomaly, found and named — not acted upon (FR-046). A validator
 * returns this; it never writes, never repairs, and never decides whether
 * the row is copied. The job hands it to `ImportErrorRecorder`, which is
 * the only thing that writes to `import_errors`.
 *
 * `severity` is not carried here: it belongs to the code (FR-044), and
 * duplicating it would let one site disagree with another about how serious
 * the same finding is.
 *
 * **`context` must never carry a `user_id`** (FR-020). Nothing in the bank
 * tables has one, which is why these validators are safe by construction —
 * but the rule is the payload's, not the table's, and it holds for anything
 * added here later.
 */
final readonly class Finding
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public ImportErrorCode $code,
        public string $sourceTable,
        public ?int $sourceId,
        public string $message,
        public array $context = [],
    ) {}
}

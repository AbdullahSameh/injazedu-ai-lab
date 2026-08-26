<?php

namespace App\Support\Import;

use App\Models\ImportError;
use App\Models\ImportRun;

/**
 * Writes one anomaly and returns, so the calling batch continues (FR-020,
 * FR-027). A silent `try/catch` around a validator call is a defect — the
 * validator must call this recorder, not swallow the anomaly.
 *
 * Built empty here on purpose: Phase 5's thirteen validators (T067–T069)
 * are what actually call `record()`, through the hook T070 wires into the
 * bank jobs. `code` and `severity` are plain strings until `ImportErrorCode`
 * (T066) exists; nothing here depends on that enum.
 *
 * `context` must never carry a `user_id` — hashing happens at read time,
 * before any error path can see the raw value.
 */
final class ImportErrorRecorder
{
    public function __construct(private readonly ImportRun $run) {}

    public function record(
        string $code,
        string $severity,
        string $sourceTable,
        ?int $sourceId,
        string $message,
        array $context = [],
    ): void {
        ImportError::create([
            'import_run_id' => $this->run->id,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'context' => $context,
        ]);
    }
}

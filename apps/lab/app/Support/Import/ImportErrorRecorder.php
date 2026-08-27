<?php

namespace App\Support\Import;

use App\Models\ImportRun;
use App\Support\Import\Validators\Finding;
use Illuminate\Support\Facades\DB;

/**
 * The only thing that writes to `import_errors` (FR-027). Validators find
 * anomalies and return them; this records them and returns, so the calling
 * batch continues. A silent `try/catch` around a validator call is a defect
 * — the finding must reach this class, not be swallowed at the call site.
 *
 * **Buffered, because the volume is real.** `OPTION_ORDER_TIE` alone fires
 * on 29,075 of the bank's 29,142 questions (query 5), and a full bank pass
 * produces ~29,600 findings. One `INSERT` per finding is ~30,000 round
 * trips; batching them into statements of `FLUSH_SIZE` turns the whole
 * validation pass into a rounding error against the import it rides on.
 * `flush()` must be called at the end of a pass — {@see AppliesValidators}
 * does it, and the counter on `import_runs` only becomes true once it has.
 *
 * `error_count` is incremented per flush rather than per row for the same
 * reason.
 *
 * `context` must never carry a `user_id` (FR-020) — hashing happens at read
 * time, before any error path can see the raw value. Nothing in the bank
 * tables has one, so these findings are safe by construction; the rule
 * belongs to the payload, not to the table.
 */
final class ImportErrorRecorder
{
    /**
     * Rows per INSERT. Postgres caps a statement at 65,535 bound
     * parameters; at 8 columns a 1,000-row batch binds 8,000.
     */
    public const FLUSH_SIZE = 1000;

    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    public function __construct(private readonly ImportRun $run) {}

    /**
     * Record one anomaly. Kept as the primitive so a caller with no
     * `Finding` to hand — a failure path outside the thirteen checks — can
     * still log rather than stay quiet.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $code,
        string $severity,
        string $sourceTable,
        ?int $sourceId,
        string $message,
        array $context = [],
    ): void {
        $this->buffer[] = [
            'import_run_id' => $this->run->id,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ];

        if (count($this->buffer) >= self::FLUSH_SIZE) {
            $this->flush();
        }
    }

    /**
     * Record what the thirteen checks found. Severity is read from the code
     * (FR-044) and never passed in, so no call site can disagree with
     * another about how serious the same finding is.
     */
    public function recordFinding(Finding $finding): void
    {
        $this->record(
            $finding->code->value,
            $finding->code->severity(),
            $finding->sourceTable,
            $finding->sourceId,
            $finding->message,
            $finding->context,
        );
    }

    /** @param  list<Finding>  $findings */
    public function recordFindings(array $findings): void
    {
        foreach ($findings as $finding) {
            $this->recordFinding($finding);
        }
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $count = count($this->buffer);

        DB::connection('pgsql')->table('import_errors')->insert($this->buffer);
        $this->run->increment('error_count', $count);

        $this->buffer = [];
    }
}

<?php

namespace Tests\Feature;

use App\Exceptions\SourceTableNotAllowed;
use App\Support\SourceReader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-003 / SC-001: each of the fifteen doubly-forbidden tables is refused by
 * its own name, one assertion each. The names are enumerated explicitly — never
 * derived from the database or an allowlist — so a future change widening
 * config('lab.source_tables') or config('lab.profile_tables') cannot pass silently.
 *
 * Why fifteen, not seventeen: on 2026-08-23 (P0 §3.2) `orders` and
 * `course_order` joined `profile_tables`, so they became readable as counts.
 * They are NOT copyable — that property is asserted in
 * SourceTableAllowlistTest, which is what stops this shrinking list from
 * becoming an eroding allowlist.
 *
 * The 2026-08-26 split (ADR-022) moved `question_result` from
 * `source_tables` to `profile_tables`. That does not change this list —
 * the table was already readable and is not newly exposed; what changed is
 * that it is no longer copyable, which is a strengthening, asserted next
 * door.
 *
 * Runs with the service and the model runtime both stopped: the refusal
 * happens before any connection is opened.
 */
class ForbiddenTableRefusalTest extends TestCase
{
    /**
     * The fifteen doubly-forbidden names (spec.md §3.2). Fixed list on purpose.
     *
     * @return array<string, array{string}>
     */
    public static function forbiddenTables(): array
    {
        return [
            'users' => ['users'],
            'book_order' => ['book_order'],
            'coupons' => ['coupons'],
            'certificates' => ['certificates'],
            'complaints' => ['complaints'],
            'complaint_responses' => ['complaint_responses'],
            'social_providers' => ['social_providers'],
            'personal_access_tokens' => ['personal_access_tokens'],
            'paymob_logs' => ['paymob_logs'],
            'zoom_users' => ['zoom_users'],
            'audits' => ['audits'],
            'telescope_entries' => ['telescope_entries'],
            'google_oauth_tokens' => ['google_oauth_tokens'],
            'failed_jobs' => ['failed_jobs'],
            'settings' => ['settings'],
        ];
    }

    #[DataProvider('forbiddenTables')]
    public function test_forbidden_table_is_refused_naming_it(string $table): void
    {
        $reader = app(SourceReader::class);

        try {
            $reader->table($table);
            $this->fail("SourceReader accepted forbidden table [{$table}]");
        } catch (SourceTableNotAllowed $e) {
            $this->assertStringContainsString($table, $e->getMessage());
        }
    }
}

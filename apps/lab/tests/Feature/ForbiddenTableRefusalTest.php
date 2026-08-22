<?php

namespace Tests\Feature;

use App\Exceptions\SourceTableNotAllowed;
use App\Support\SourceReader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-021 / SC-011: each of the seventeen forbidden tables is refused by its
 * own name, one assertion each. The names are enumerated explicitly — never
 * derived from the database or the allowlist — so a future change widening
 * config('lab.source_tables') cannot pass silently.
 *
 * Runs with the service and the model runtime both stopped: the refusal
 * happens before any connection is opened.
 */
class ForbiddenTableRefusalTest extends TestCase
{
    /**
     * The seventeen forbidden names (spec.md §14.2). Fixed list on purpose.
     *
     * @return array<string, array{string}>
     */
    public static function forbiddenTables(): array
    {
        return [
            'users' => ['users'],
            'orders' => ['orders'],
            'course_order' => ['course_order'],
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

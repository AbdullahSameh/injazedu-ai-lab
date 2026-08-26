<?php

namespace Tests\Feature;

use App\Exceptions\SourceTableNotAllowed;
use App\Support\SourceReader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SC-001 / SC-002 / FR-004: the allowlist split is safe, not merely wider.
 *
 * Three properties, each asserted per table:
 *  - every source table is READABLE and COPYABLE;
 *  - every profile table is READABLE but NEVER COPYABLE — reading a count is
 *    not storing a row;
 *  - a table on neither list is refused for reading, naming it.
 *
 * Ten copyable and seven profile-only since 2026-08-26 (ADR-022):
 * `question_result` moved from the first list to the second when the raw
 * answer mirror was replaced by derived statistics. That move is what makes
 * "no raw answer rows are stored" structural — the profile-only assertion
 * below is the guarantee, not a convention.
 *
 * Runs with the service and the model runtime both stopped: the assertions
 * happen before any connection is opened.
 */
class SourceTableAllowlistTest extends TestCase
{
    public function test_every_source_table_is_readable_and_copyable(): void
    {
        $reader = app(SourceReader::class);
        $tables = config('lab.source_tables');

        $this->assertCount(10, $tables);

        foreach ($tables as $table) {
            $reader->assertReadable($table);
            $reader->assertCopyable($table);
            $this->addToAssertionCount(2);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function profileTables(): array
    {
        return [
            'course_user' => ['course_user'],
            'course_order' => ['course_order'],
            'orders' => ['orders'],
            'user_roles' => ['user_roles'],
            'roles' => ['roles'],
            'book_course' => ['book_course'],
            'question_result' => ['question_result'],
        ];
    }

    #[DataProvider('profileTables')]
    public function test_profile_table_is_readable(string $table): void
    {
        app(SourceReader::class)->assertReadable($table);

        $this->assertContains($table, config('lab.profile_tables'));
    }

    #[DataProvider('profileTables')]
    public function test_profile_table_is_never_copyable(string $table): void
    {
        try {
            app(SourceReader::class)->assertCopyable($table);
            $this->fail("SourceReader offered to copy profile-only table [{$table}]");
        } catch (SourceTableNotAllowed $e) {
            $this->assertStringContainsString($table, $e->getMessage());
        }
    }

    public function test_a_table_on_neither_list_is_refused_naming_it(): void
    {
        $reader = app(SourceReader::class);

        try {
            $reader->table('users');
            $this->fail('SourceReader accepted a table outside both allowlists');
        } catch (SourceTableNotAllowed $e) {
            $this->assertStringContainsString('users', $e->getMessage());
        }
    }
}

<?php

namespace Tests\Feature;

use App\Exceptions\QueryFileMalformed;
use App\Models\SourceSnapshot;
use App\Support\Profiling\QueryFile;
use App\Support\Profiling\QueryPack;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * FR-002 / FR-003 / SC-002: the §6 pack's declaration is enforced, not
 * decorative. The eighteen file headers must agree with
 * sql/profiling/README.md (notes.md N5 — the two declarations must never
 * drift), a file with no parseable header is a hard failure, a forbidden
 * declared table stops the command before any SQL executes, and --dry-run
 * truly executes none.
 */
class ProfileDeclarationTest extends TestCase
{
    public function test_eighteen_file_headers_match_the_readme_table(): void
    {
        $files = (new QueryPack)->files();
        $this->assertCount(18, $files);

        $readmePath = rtrim(config('lab.profiling.sql_path'), '/').'/README.md';
        $readme = file_get_contents($readmePath);
        $this->assertNotFalse($readme, "Could not read {$readmePath}");

        preg_match_all(
            '/^\|\s*(\d+)\s*\|\s*`([^`]+)`\s*\|\s*(.+?)\s*\|\s*(.+?)\s*\|$/m',
            $readme,
            $rowMatches,
            PREG_SET_ORDER
        );
        $this->assertCount(18, $rowMatches, 'README table row count drifted from 18');

        $expected = [];
        foreach ($rowMatches as $row) {
            preg_match_all('/`([^`]+)`/', $row[3], $tableMatches);

            $expected[(int) $row[1]] = [
                'filename' => $row[2],
                'tables' => $tableMatches[1],
                'allowlist' => str_contains(strtolower($row[4]), 'profile') ? 'profile-only' : 'copy',
            ];
        }

        foreach ($files as $file) {
            $this->assertArrayHasKey($file->number, $expected, "README has no row for query {$file->number}");
            $this->assertSame($expected[$file->number]['filename'], $file->filename);
            $this->assertSame($expected[$file->number]['tables'], $file->tablesRead);
            $this->assertSame($expected[$file->number]['allowlist'], $file->allowlist);
        }
    }

    public function test_a_file_with_no_header_is_a_hard_failure(): void
    {
        $this->expectException(QueryFileMalformed::class);

        QueryFile::fromFile(base_path('tests/Fixtures/profiling-no-header/01-no-header.sql'));
    }

    public function test_dry_run_executes_nothing(): void
    {
        $before = SourceSnapshot::count();

        $exitCode = Artisan::call('lab:profile', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame($before, SourceSnapshot::count());
    }

    public function test_a_declared_forbidden_table_stops_the_command_before_any_sql_executes(): void
    {
        config(['lab.profiling.sql_path' => base_path('tests/Fixtures/profiling-forbidden')]);

        $before = SourceSnapshot::count();

        $exitCode = Artisan::call('lab:profile');

        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('users', Artisan::output());
        $this->assertSame($before, SourceSnapshot::count(), 'A row was written despite the forbidden-table refusal');
    }
}

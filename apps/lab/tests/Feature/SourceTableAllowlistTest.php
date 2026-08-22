<?php

namespace Tests\Feature;

use App\Exceptions\SourceTableNotAllowed;
use App\Support\SourceReader;
use Tests\TestCase;

/**
 * SC-004: each of the eleven allowlisted tables is accepted; a table outside
 * the list throws, naming it.
 */
class SourceTableAllowlistTest extends TestCase
{
    public function test_every_allowlisted_table_is_accepted(): void
    {
        $reader = app(SourceReader::class);
        $tables = config('lab.source_tables');

        $this->assertCount(11, $tables);

        foreach ($tables as $table) {
            $reader->assertAllowed($table);
            $this->addToAssertionCount(1);
        }
    }

    public function test_a_table_outside_the_list_throws_naming_it(): void
    {
        $reader = app(SourceReader::class);

        try {
            $reader->table('users');
            $this->fail('SourceReader accepted a table outside the allowlist');
        } catch (SourceTableNotAllowed $e) {
            $this->assertStringContainsString('users', $e->getMessage());
        }
    }
}

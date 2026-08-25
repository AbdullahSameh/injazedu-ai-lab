<?php

namespace Tests\Feature;

use App\Exceptions\ReadOnlyViolation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SC-002: INSERT, UPDATE, and DELETE through the `injazedu` connection each
 * throw, and zero rows change.
 *
 * The statements are deliberately no-ops if they ever executed (WHERE 1=0, or
 * an unknown column), so a guard failure fails the assertion on the exception
 * type without touching a row — the count assertions then prove it.
 */
class ReadOnlyGuardTest extends TestCase
{
    public function test_insert_throws_and_changes_nothing(): void
    {
        $before = DB::connection('injazedu')->table('questions')->count();

        try {
            DB::connection('injazedu')->insert(
                'INSERT INTO questions (nonexistent_column_xyz) VALUES (1)'
            );
            $this->fail('INSERT through the injazedu connection was not blocked');
        } catch (ReadOnlyViolation) {
            // expected — guard 2 threw before execution
        }

        $this->assertSame($before, DB::connection('injazedu')->table('questions')->count());
    }

    public function test_update_throws_and_changes_nothing(): void
    {
        $before = DB::connection('injazedu')->table('questions')->count();

        try {
            DB::connection('injazedu')->update(
                'UPDATE questions SET id = id WHERE 1 = 0'
            );
            $this->fail('UPDATE through the injazedu connection was not blocked');
        } catch (ReadOnlyViolation) {
            // expected — guard 2 threw before execution
        }

        $this->assertSame($before, DB::connection('injazedu')->table('questions')->count());
    }

    public function test_delete_throws_and_changes_nothing(): void
    {
        $before = DB::connection('injazedu')->table('questions')->count();

        try {
            DB::connection('injazedu')->delete(
                'DELETE FROM questions WHERE 1 = 0'
            );
            $this->fail('DELETE through the injazedu connection was not blocked');
        } catch (ReadOnlyViolation) {
            // expected — guard 2 threw before execution
        }

        $this->assertSame($before, DB::connection('injazedu')->table('questions')->count());
    }
}

<?php

namespace Tests\Feature;

use App\Exceptions\SourceTableNotAllowed;
use App\Support\Import\BatchUpsert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * FR-023 / FR-024 / FR-026 / SC-007: the one write path is idempotent, and
 * it is guarded.
 *
 * Every mirror row in the Lab goes through `BatchUpsert::run()`. The three
 * outcomes it reports are what `lab:import` counts and what makes a re-run
 * cheap: a matching `payload_hash` must write nothing at all, not merely
 * write the same bytes again.
 *
 * Runs against a scratch table created and dropped here, so the assertions
 * are made on the real PostgreSQL upsert — the `ON CONFLICT … WHERE … IS
 * DISTINCT FROM` statement and the `RETURNING (xmax = 0)` counting trick —
 * without touching a mirror table the rest of the suite reads.
 */
class BatchUpsertIdempotencyTest extends TestCase
{
    private const PROBE = 'lab_batch_upsert_probe';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('pgsql')->dropIfExists(self::PROBE);
        DB::connection('pgsql')->statement(sprintf(
            'CREATE TABLE %s (
                id BIGSERIAL PRIMARY KEY,
                payload TEXT,
                source_system TEXT NOT NULL,
                source_id BIGINT NOT NULL,
                payload_hash CHAR(64) NOT NULL,
                strict_hash CHAR(64),
                fuzzy_hash CHAR(64),
                normalizer_version TEXT,
                fuzzy_rules_version TEXT,
                UNIQUE (source_system, source_id)
            )', self::PROBE
        ));
    }

    protected function tearDown(): void
    {
        Schema::connection('pgsql')->dropIfExists(self::PROBE);

        parent::tearDown();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $payload = 'a'): array
    {
        return array_map(fn (int $i): array => [
            'payload' => $payload,
            'source_system' => 'injazedu_production',
            'source_id' => $i,
            'payload_hash' => hash('sha256', $payload.$i),
        ], range(1, 5));
    }

    /** @return list<array<string, mixed>> */
    private function derivedRows(
        string $strictHash = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        string $fuzzyHash = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        string $normalizerVersion = 'strict-v1',
        string $fuzzyRulesVersion = 'fuzzy-v1',
    ): array {
        return [[
            'source_system' => 'injazedu_production',
            'source_id' => 1,
            'payload_hash' => str_repeat('a', 64),
            'strict_hash' => $strictHash,
            'fuzzy_hash' => $fuzzyHash,
            'normalizer_version' => $normalizerVersion,
            'fuzzy_rules_version' => $fuzzyRulesVersion,
        ]];
    }

    public function test_first_run_inserts_and_second_run_changes_nothing(): void
    {
        $upsert = app(BatchUpsert::class);

        $first = $upsert->run('questions', self::PROBE, $this->rows());
        $this->assertSame(['inserted' => 5, 'updated' => 0, 'unchanged' => 0], $first);

        $second = $upsert->run('questions', self::PROBE, $this->rows());
        $this->assertSame(
            ['inserted' => 0, 'updated' => 0, 'unchanged' => 5],
            $second,
            'A re-run over identical payloads must write nothing at all.'
        );

        $this->assertSame(5, DB::connection('pgsql')->table(self::PROBE)->count());
    }

    public function test_a_changed_payload_updates_and_an_unchanged_one_does_not(): void
    {
        $upsert = app(BatchUpsert::class);
        $upsert->run('questions', self::PROBE, $this->rows());

        // Same hashes -> the WHERE suppresses every UPDATE.
        $again = $upsert->run('questions', self::PROBE, $this->rows());
        $this->assertSame(5, $again['unchanged']);
        $this->assertSame(0, $again['inserted']);
        $this->assertSame(0, $again['updated']);

        // Different content -> different hash -> a real update, no duplicates.
        $changed = $upsert->run('questions', self::PROBE, $this->rows('b'));
        $this->assertSame(5, $changed['updated']);
        $this->assertSame(0, $changed['inserted']);

        $this->assertSame(5, DB::connection('pgsql')->table(self::PROBE)->count());
        $this->assertSame('b', DB::connection('pgsql')->table(self::PROBE)->value('payload'));
    }

    public function test_derived_upsert_updates_an_existing_row_when_only_one_strict_or_fuzzy_field_changes(): void
    {
        $upsert = app(BatchUpsert::class);
        $comparisonColumns = ['strict_hash', 'fuzzy_hash', 'normalizer_version', 'fuzzy_rules_version'];

        $first = $upsert->runDerived(self::PROBE, $this->derivedRows(), ['source_system', 'source_id'], $comparisonColumns);
        $this->assertSame(['inserted' => 1, 'updated' => 0, 'unchanged' => 0], $first);

        $fuzzyHashOnly = $upsert->runDerived(
            self::PROBE,
            $this->derivedRows(fuzzyHash: str_repeat('b', 64)),
            ['source_system', 'source_id'],
            $comparisonColumns,
        );
        $this->assertSame(['inserted' => 0, 'updated' => 1, 'unchanged' => 0], $fuzzyHashOnly);
        $this->assertSame(str_repeat('a', 64), DB::connection('pgsql')->table(self::PROBE)->value('strict_hash'));
        $this->assertSame(str_repeat('b', 64), DB::connection('pgsql')->table(self::PROBE)->value('fuzzy_hash'));

        $strictHashOnly = $upsert->runDerived(
            self::PROBE,
            $this->derivedRows(strictHash: str_repeat('c', 64), fuzzyHash: str_repeat('b', 64)),
            ['source_system', 'source_id'],
            $comparisonColumns,
        );
        $this->assertSame(['inserted' => 0, 'updated' => 1, 'unchanged' => 0], $strictHashOnly);
        $this->assertSame(str_repeat('c', 64), DB::connection('pgsql')->table(self::PROBE)->value('strict_hash'));
        $this->assertSame(str_repeat('b', 64), DB::connection('pgsql')->table(self::PROBE)->value('fuzzy_hash'));
        $this->assertSame('strict-v1', DB::connection('pgsql')->table(self::PROBE)->value('normalizer_version'));
        $this->assertSame('fuzzy-v1', DB::connection('pgsql')->table(self::PROBE)->value('fuzzy_rules_version'));

        $strictVersionOnly = $upsert->runDerived(
            self::PROBE,
            $this->derivedRows(strictHash: str_repeat('c', 64), fuzzyHash: str_repeat('b', 64), normalizerVersion: 'strict-v2'),
            ['source_system', 'source_id'],
            $comparisonColumns,
        );
        $this->assertSame(['inserted' => 0, 'updated' => 1, 'unchanged' => 0], $strictVersionOnly);
        $this->assertSame('strict-v2', DB::connection('pgsql')->table(self::PROBE)->value('normalizer_version'));

        $fuzzyVersionOnly = $upsert->runDerived(
            self::PROBE,
            $this->derivedRows(strictHash: str_repeat('c', 64), fuzzyHash: str_repeat('b', 64), normalizerVersion: 'strict-v2', fuzzyRulesVersion: 'fuzzy-v2'),
            ['source_system', 'source_id'],
            $comparisonColumns,
        );
        $this->assertSame(['inserted' => 0, 'updated' => 1, 'unchanged' => 0], $fuzzyVersionOnly);
        $this->assertSame('fuzzy-v2', DB::connection('pgsql')->table(self::PROBE)->value('fuzzy_rules_version'));
    }

    public function test_the_write_path_refuses_a_table_that_is_not_copyable(): void
    {
        // question_result is profile-only since 2026-08-26 (ADR-022): readable
        // as aggregates, never storable row-for-row. This is the guard that
        // makes that structural rather than conventional.
        $this->expectException(SourceTableNotAllowed::class);

        app(BatchUpsert::class)->run('question_result', self::PROBE, $this->rows());
    }
}

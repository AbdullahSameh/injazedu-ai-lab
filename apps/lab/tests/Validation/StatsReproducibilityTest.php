<?php

namespace Tests\Validation;

use App\Support\Derive\PayloadHasher;
use Illuminate\Support\Facades\DB;

/**
 * Constitution §V: "Every statistical output must be reproducible from raw
 * rows, and a sample-based test must prove it." This is that test.
 *
 * Since 2026-08-26 (ADR-022) the Lab does not keep a copy of the 13.8M raw
 * answer rows — `source_item_stats` and `source_option_stats` are computed
 * by pushing a GROUP BY into the source. The raw rows still exist, in the
 * fixed 2026-08-07 snapshot, which is what the reproducibility guarantee
 * now runs against: constitution §III already defines reproducible as
 * "re-import from the snapshot, re-run the pipeline".
 *
 * So this recomputes a sample of stored statistics straight from
 * `question_result` and `results` and demands they agree. It deliberately
 * does NOT compare one MySQL aggregate against another: the whole class of
 * bug this guards is MySQL quantizing a ratio to 4 decimal places, which
 * two aggregates would share and agree on. The comparison is against raw
 * rows summed here, in PHP.
 */
class StatsReproducibilityTest extends TestCase
{
    private const SAMPLE = 25;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.pgsql.database' => 'injazedu_lab']);
        DB::purge('pgsql');
    }

    public function test_item_statistics_recompute_from_raw_rows(): void
    {
        $sample = DB::connection('pgsql')
            ->table('source_item_stats')
            ->where('scope', 'active')
            ->where('n', '>', 0)
            ->orderBy('question_source_id')
            ->limit(self::SAMPLE)
            ->get();

        $this->assertCount(self::SAMPLE, $sample, 'No item statistics to verify — run lab:import first.');

        foreach ($sample as $stat) {
            // Raw answer rows for this question, straight from the source.
            $raw = DB::connection('injazedu')->select(
                'SELECT qr.points, r.total_points
                 FROM question_result qr JOIN results r ON r.id = qr.result_id
                 WHERE qr.question_id = ? AND r.deleted_at IS NULL',
                [$stat->question_source_id]
            );

            $n = count($raw);
            $correct = array_values(array_filter($raw, static fn ($row): bool => $row->points > 0));
            $wrong = array_values(array_filter($raw, static fn ($row): bool => $row->points <= 0));

            $this->assertSame((int) $stat->n, $n, "n mismatch on question {$stat->question_source_id}");
            $this->assertSame((int) $stat->n_correct, count($correct));
            $this->assertEqualsWithDelta(count($correct) / $n, (float) $stat->p_value, 1e-9);

            // Corrected total: the attempt's total minus this item's own score.
            $corrected = static fn (array $rows): array => array_map(
                static fn ($row): float => (float) ($row->total_points - $row->points),
                $rows
            );

            if ($correct !== []) {
                $m1 = array_sum($corrected($correct)) / count($correct);
                $this->assertEqualsWithDelta($m1, (float) $stat->m1_corrected, 1e-6);
            }

            if ($wrong !== []) {
                $m0 = array_sum($corrected($wrong)) / count($wrong);
                $this->assertEqualsWithDelta($m0, (float) $stat->m0_corrected, 1e-6);
            }
        }
    }

    public function test_option_shares_recompute_from_raw_rows(): void
    {
        $questionIds = DB::connection('pgsql')
            ->table('source_item_stats')
            ->where('scope', 'active')->where('n', '>', 0)
            ->orderBy('question_source_id')->limit(self::SAMPLE)
            ->pluck('question_source_id');

        foreach ($questionIds as $questionId) {
            $raw = DB::connection('injazedu')->select(
                'SELECT qr.option_id, COUNT(*) AS chosen
                 FROM question_result qr JOIN results r ON r.id = qr.result_id
                 WHERE qr.question_id = ? AND r.deleted_at IS NULL
                 GROUP BY qr.option_id',
                [$questionId]
            );

            $counts = [];
            $total = 0;

            foreach ($raw as $row) {
                $counts[(int) $row->option_id] = (int) $row->chosen;
                $total += (int) $row->chosen;
            }

            $stored = DB::connection('pgsql')->table('source_option_stats')
                ->where('question_source_id', $questionId)->where('scope', 'active')->get();

            $this->assertNotEmpty($stored, "No option stats for question {$questionId}");

            $sumShare = 0.0;

            foreach ($stored as $optionStat) {
                $expected = $counts[(int) $optionStat->option_source_id] ?? 0;

                $this->assertSame(
                    $expected,
                    (int) $optionStat->chosen_n,
                    "chosen_n mismatch on option {$optionStat->option_source_id}"
                );
                $this->assertEqualsWithDelta($expected / $total, (float) $optionStat->chosen_share, 1e-9);

                $sumShare += (float) $optionStat->chosen_share;
            }

            $this->assertEqualsWithDelta(1.0, $sumShare, 1e-9, "Shares must total 1 for question {$questionId}");
        }
    }

    public function test_never_chosen_options_are_present_with_a_zero_count(): void
    {
        // 37% of options were never chosen by anyone. They must still appear,
        // or "a distractor chosen by under 2% is dead" can never fire.
        $zero = DB::connection('pgsql')->table('source_option_stats')
            ->where('scope', 'active')->where('chosen_n', 0)->count();

        $this->assertGreaterThan(0, $zero);

        $this->assertSame(
            DB::connection('pgsql')->table('source_question_options')->count(),
            DB::connection('pgsql')->table('source_option_stats')->where('scope', 'active')->count(),
            'Every option needs a stats row per scope, chosen or not.'
        );
    }

    public function test_stats_hash_is_a_faithful_function_of_the_stored_columns(): void
    {
        // What makes a re-run report "unchanged" instead of rewriting every
        // row: the hash must depend on the content and nothing else.
        $hasher = new PayloadHasher;

        $sample = DB::connection('pgsql')->table('source_item_stats')
            ->orderBy('question_source_id')->limit(200)->get();

        foreach ($sample as $stat) {
            $expected = $hasher->hash([
                'n' => (int) $stat->n,
                'n_correct' => (int) $stat->n_correct,
                'p_value' => $stat->p_value === null ? null : (float) $stat->p_value,
                'm1_corrected' => $stat->m1_corrected === null ? null : (float) $stat->m1_corrected,
                'm0_corrected' => $stat->m0_corrected === null ? null : (float) $stat->m0_corrected,
                'sd_corrected' => $stat->sd_corrected === null ? null : (float) $stat->sd_corrected,
            ]);

            $this->assertSame($expected, $stat->stats_hash, "stats_hash drift on question {$stat->question_source_id}");
        }
    }
}

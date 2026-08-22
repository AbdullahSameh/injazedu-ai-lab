<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\DB;

final class VectorRoundTripCheck extends AbstractHealthCheck
{
    public function number(): int
    {
        return 7;
    }

    public function name(): string
    {
        return 'pgvector round-trip';
    }

    public function target(): string
    {
        return 'postgres:5433';
    }

    public function expectation(): string
    {
        return CheckResult::MUST_SUCCEED;
    }

    public function run(): CheckResult
    {
        $vector = [];
        for ($index = 0; $index < 768; $index++) {
            $vector[] = (($index % 17) - 8) / 16;
        }
        $encoded = json_encode($vector, JSON_THROW_ON_ERROR);

        DB::connection('pgsql')->table('lab_vector_probes')->updateOrInsert(
            ['id' => 1],
            ['embedding' => $encoded, 'written_at' => now()],
        );

        $row = DB::connection('pgsql')->table('lab_vector_probes')
            ->selectRaw('embedding::text AS embedding')
            ->where('id', 1)
            ->first();

        if ($row === null || $row->embedding !== $encoded) {
            return $this->fail('postgres:5433 returned a vector different from the 768-float probe');
        }

        if (DB::connection('pgsql')->table('lab_vector_probes')->count() !== 1) {
            return $this->fail('lab_vector_probes contains more than the fixed id 1 row');
        }

        return $this->pass('postgres:5433 stored and returned 768 deterministic floats exactly');
    }
}

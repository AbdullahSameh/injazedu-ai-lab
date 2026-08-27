<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VectorProbeSchemaTest extends TestCase
{
    public function test_vector_probe_table_has_the_fixed_non_pii_shape_and_768_dimensions(): void
    {
        $columns = DB::connection('pgsql')->select(<<<'SQL'
            SELECT a.attname AS column_name, format_type(a.atttypid, a.atttypmod) AS data_type
            FROM pg_attribute a
            JOIN pg_class c ON c.oid = a.attrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND c.relname = 'lab_vector_probes'
              AND a.attnum > 0
              AND NOT a.attisdropped
            ORDER BY a.attnum
            SQL);

        $shape = [];
        foreach ($columns as $column) {
            $shape[$column->column_name] = $column->data_type;
        }

        $this->assertSame([
            'id' => 'integer',
            'embedding' => 'vector(768)',
            'written_at' => 'timestamp(0) without time zone',
        ], $shape);
    }
}

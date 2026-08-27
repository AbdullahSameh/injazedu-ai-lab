<?php

namespace App\Support\Profiling;

/**
 * Discovers the §6 query pack in `sql/profiling/` and returns it as
 * eighteen QueryFile instances, sorted by the number parsed from each
 * file's own header — numeric order, not lexical, so a future file 19
 * cannot sort between 1 and 2 (FR-001).
 */
final class QueryPack
{
    /**
     * @return list<QueryFile>
     */
    public function files(): array
    {
        $path = config('lab.profiling.sql_path');

        $paths = glob(rtrim($path, '/').'/*.sql') ?: [];

        $files = array_map(
            fn (string $file) => QueryFile::fromFile($file),
            $paths
        );

        usort($files, fn (QueryFile $a, QueryFile $b) => $a->number <=> $b->number);

        return array_values($files);
    }
}

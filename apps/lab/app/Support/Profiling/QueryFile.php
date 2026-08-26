<?php

namespace App\Support\Profiling;

use App\Exceptions\QueryFileMalformed;

/**
 * One `sql/profiling/*.sql` file: its declared tables and allowlist status
 * (parsed from its own header, per notes.md N5), and the executable
 * statement with the leading `--` comment header stripped.
 *
 * Guard 2 (AppServiceProvider::boot()) takes the first token of the
 * statement sent to the `injazedu` connection; every file in the pack opens
 * with a `--` header, so handing the raw file contents to `DB::select()`
 * throws `ReadOnlyViolation` on a pure `SELECT` (notes.md N1). Stripping
 * happens here, once, so every caller gets an executable statement.
 */
final class QueryFile
{
    /**
     * @param  list<string>  $tablesRead
     */
    public function __construct(
        public readonly int $number,
        public readonly string $filename,
        public readonly string $title,
        public readonly array $tablesRead,
        public readonly string $allowlist,
        public readonly string $statement,
    ) {}

    public static function fromFile(string $path): self
    {
        $filename = basename($path);
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new QueryFileMalformed("Query file [{$filename}] could not be read.");
        }

        if (! preg_match('/^(\d+)-/', $filename, $numberMatch)) {
            throw QueryFileMalformed::missingHeader($filename, 'NN-slug.sql filename');
        }

        if (! preg_match('/^--\s*Tables read\s*:\s*(.+)$/mi', $contents, $tablesMatch)) {
            throw QueryFileMalformed::missingHeader($filename, 'Tables read');
        }

        if (! preg_match('/^--\s*Allowlist\s*:\s*(\S+)/mi', $contents, $allowlistMatch)) {
            throw QueryFileMalformed::missingHeader($filename, 'Allowlist');
        }

        $tablesRead = array_values(array_filter(array_map(
            trim(...),
            explode(',', $tablesMatch[1])
        )));

        $allowlist = str_starts_with(strtolower($allowlistMatch[1]), 'profile')
            ? 'profile-only'
            : 'copy';

        return new self(
            number: (int) $numberMatch[1],
            filename: $filename,
            title: self::deriveTitle($filename),
            tablesRead: $tablesRead,
            allowlist: $allowlist,
            statement: self::stripHeader($contents),
        );
    }

    private static function deriveTitle(string $filename): string
    {
        $slug = preg_replace('/^\d+-/', '', $filename);
        $slug = preg_replace('/\.sql$/', '', $slug);

        return str_replace('-', ' ', $slug);
    }

    private static function stripHeader(string $contents): string
    {
        $lines = explode("\n", $contents);

        $bodyStart = 0;
        foreach ($lines as $index => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                $bodyStart = $index + 1;

                continue;
            }

            break;
        }

        return trim(implode("\n", array_slice($lines, $bodyStart)));
    }
}

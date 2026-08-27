<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Blocks migrate:fresh / migrate:refresh / migrate:reset / db:wipe / DROP
 * DATABASE / DROP SCHEMA ... CASCADE against any database not named in
 * config('lab.safe_destructive_databases') — never the developer's real
 * injazedu_lab (CLAUDE.md, constitution §III, amended 2026-08-27).
 */
class DestructiveOperationBlocked extends RuntimeException
{
    public static function forCommand(string $command, string $connection, ?string $database): self
    {
        return new self(
            "Refused to run '{$command}': it would reset database "
            ."'{$database}' on connection '{$connection}', which is not in "
            ."config('lab.safe_destructive_databases'). If this really is the "
            .'disposable test database, check .env.testing / LAB_SAFE_DESTRUCTIVE_DATABASES.'
        );
    }

    public static function forStatement(string $query, ?string $database): self
    {
        $preview = mb_substr(preg_replace('/\s+/', ' ', trim($query)), 0, 120);

        return new self(
            "Refused destructive statement against database '{$database}', which is not in "
            ."config('lab.safe_destructive_databases'): {$preview}"
        );
    }
}

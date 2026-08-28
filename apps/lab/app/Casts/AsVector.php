<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * A pgvector column round-trips as its own text literal — `[0.1,0.2,...]` —
 * which is exactly `json_encode()`/`json_decode()` on a plain float array
 * (App\Support\Health\VectorRoundTripCheck relies on the same equivalence).
 * Laravel 13 ships `$table->vector()` for the schema side but no matching
 * Eloquent cast, so this is that missing half.
 *
 * @implements CastsAttributes<array<int, float>|null, array<int, float>|null>
 */
final class AsVector implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}

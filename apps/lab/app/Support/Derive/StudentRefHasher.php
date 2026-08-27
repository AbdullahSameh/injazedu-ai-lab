<?php

namespace App\Support\Derive;

use App\Exceptions\StudentRefPepperMissing;

/**
 * `student_ref` — `HMAC-SHA256(pepper, user_id)` (FR-019, data-model.md §2).
 * `user_id` is read, hashed and discarded in the same statement by the
 * caller; this class never sees or returns it. Throws rather than hashing
 * against an empty or missing pepper — see `StudentRefPepperMissing`.
 */
final class StudentRefHasher
{
    public function hash(int|string $userId): string
    {
        $pepper = config('lab.student_ref_pepper');

        if (empty($pepper)) {
            throw StudentRefPepperMissing::missing();
        }

        return hash_hmac('sha256', (string) $userId, $pepper);
    }
}

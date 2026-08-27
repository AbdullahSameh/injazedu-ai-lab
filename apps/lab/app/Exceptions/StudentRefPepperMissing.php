<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by App\Support\Derive\StudentRefHasher. Once ~1.1 M `student_ref`
 * values exist, changing or losing `STUDENT_REF_PEPPER` orphans every one of
 * them and there is no backup (P1 plan §8 item B) — deriving a `student_ref`
 * with no pepper configured is refused outright rather than silently hashing
 * against an empty string (FR-019).
 */
class StudentRefPepperMissing extends RuntimeException
{
    public static function missing(): self
    {
        return new self(
            'STUDENT_REF_PEPPER is not configured. Refusing to derive a student_ref: '.
            'hashing against an empty pepper is not a safe default.'
        );
    }
}

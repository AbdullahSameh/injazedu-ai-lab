<?php

namespace Tests\Unit\Derive;

use App\Exceptions\StudentRefPepperMissing;
use App\Support\Derive\StudentRefHasher;
use Tests\TestCase;

class StudentRefHasherTest extends TestCase
{
    public function test_stable_for_the_same_input_and_pepper(): void
    {
        config(['lab.student_ref_pepper' => 'pepper-one']);
        $hasher = new StudentRefHasher;

        $this->assertSame($hasher->hash(42), $hasher->hash(42));
    }

    public function test_different_pepper_produces_a_different_hash(): void
    {
        $hasher = new StudentRefHasher;

        config(['lab.student_ref_pepper' => 'pepper-one']);
        $first = $hasher->hash(42);

        config(['lab.student_ref_pepper' => 'pepper-two']);
        $second = $hasher->hash(42);

        $this->assertNotSame($first, $second);
    }

    public function test_hash_is_64_char_sha256_hex(): void
    {
        config(['lab.student_ref_pepper' => 'pepper-one']);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (new StudentRefHasher)->hash(42));
    }

    public function test_throws_on_an_empty_pepper(): void
    {
        config(['lab.student_ref_pepper' => '']);

        $this->expectException(StudentRefPepperMissing::class);

        (new StudentRefHasher)->hash(42);
    }

    public function test_throws_on_a_missing_pepper(): void
    {
        config(['lab.student_ref_pepper' => null]);

        $this->expectException(StudentRefPepperMissing::class);

        (new StudentRefHasher)->hash(42);
    }
}

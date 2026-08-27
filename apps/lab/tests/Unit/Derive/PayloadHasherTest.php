<?php

namespace Tests\Unit\Derive;

use App\Support\Derive\PayloadHasher;
use PHPUnit\Framework\TestCase;

class PayloadHasherTest extends TestCase
{
    public function test_same_input_produces_the_same_hash(): void
    {
        $hasher = new PayloadHasher;
        $columns = ['name' => 'Algebra', 'slug' => 'algebra', 'parent_source_id' => 3];

        $this->assertSame($hasher->hash($columns), $hasher->hash($columns));
    }

    public function test_key_order_does_not_affect_the_hash(): void
    {
        $hasher = new PayloadHasher;

        $a = $hasher->hash(['name' => 'Algebra', 'slug' => 'algebra']);
        $b = $hasher->hash(['slug' => 'algebra', 'name' => 'Algebra']);

        $this->assertSame($a, $b);
    }

    public function test_a_non_question_table_hashes_its_own_columns(): void
    {
        $hasher = new PayloadHasher;

        $unchanged = $hasher->hash(['name' => 'Algebra', 'slug' => 'algebra']);
        $changed = $hasher->hash(['name' => 'Geometry', 'slug' => 'algebra']);

        $this->assertNotSame($unchanged, $changed);
    }

    public function test_hash_question_is_64_char_sha256_hex(): void
    {
        $hash = (new PayloadHasher)->hashQuestion('stem', 'expl', 'hint', []);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function test_hash_question_reordering_the_input_options_does_not_change_it(): void
    {
        $hasher = new PayloadHasher;

        $orderA = [
            ['option_index' => 0, 'name' => 'A', 'points' => 1],
            ['option_index' => 1, 'name' => 'B', 'points' => 0],
        ];

        $orderB = [
            ['option_index' => 1, 'name' => 'B', 'points' => 0],
            ['option_index' => 0, 'name' => 'A', 'points' => 1],
        ];

        $this->assertSame(
            $hasher->hashQuestion('stem', 'expl', 'hint', $orderA),
            $hasher->hashQuestion('stem', 'expl', 'hint', $orderB)
        );
    }

    public function test_hash_question_changing_an_options_text_changes_the_hash(): void
    {
        $hasher = new PayloadHasher;

        $options = [['option_index' => 0, 'name' => 'A', 'points' => 1]];
        $changedOptions = [['option_index' => 0, 'name' => 'A (edited)', 'points' => 1]];

        $this->assertNotSame(
            $hasher->hashQuestion('stem', 'expl', 'hint', $options),
            $hasher->hashQuestion('stem', 'expl', 'hint', $changedOptions)
        );
    }

    public function test_hash_question_changing_the_stem_changes_the_hash(): void
    {
        $hasher = new PayloadHasher;
        $options = [['option_index' => 0, 'name' => 'A', 'points' => 1]];

        $this->assertNotSame(
            $hasher->hashQuestion('stem one', 'expl', 'hint', $options),
            $hasher->hashQuestion('stem two', 'expl', 'hint', $options)
        );
    }
}

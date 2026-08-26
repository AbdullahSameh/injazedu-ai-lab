<?php

namespace Tests\Unit\Derive;

use App\Support\Derive\AnswerKeyDeriver;
use PHPUnit\Framework\TestCase;

class AnswerKeyDeriverTest extends TestCase
{
    public function test_zero_correct_options(): void
    {
        $result = (new AnswerKeyDeriver)->derive([
            ['id' => 1, 'points' => 0, 'deleted_at' => null],
            ['id' => 2, 'points' => 0, 'deleted_at' => null],
        ]);

        $this->assertSame([], $result['correct_option_ids']);
        $this->assertSame(0, $result['correct_option_count']);
        $this->assertSame('pending', $result['answer_key_state']);
    }

    public function test_one_correct_option(): void
    {
        $result = (new AnswerKeyDeriver)->derive([
            ['id' => 1, 'points' => 1, 'deleted_at' => null],
            ['id' => 2, 'points' => 0, 'deleted_at' => null],
            ['id' => 3, 'points' => 0, 'deleted_at' => null],
        ]);

        $this->assertSame([1], $result['correct_option_ids']);
        $this->assertSame(1, $result['correct_option_count']);
        $this->assertSame('pending', $result['answer_key_state']);
    }

    public function test_more_than_one_correct_option(): void
    {
        $result = (new AnswerKeyDeriver)->derive([
            ['id' => 1, 'points' => 5, 'deleted_at' => null],
            ['id' => 2, 'points' => 0, 'deleted_at' => null],
            ['id' => 3, 'points' => 2, 'deleted_at' => null],
        ]);

        $this->assertSame([1, 3], $result['correct_option_ids']);
        $this->assertSame(2, $result['correct_option_count']);
        $this->assertSame('pending', $result['answer_key_state']);
    }

    public function test_soft_deleted_options_are_excluded(): void
    {
        $result = (new AnswerKeyDeriver)->derive([
            ['id' => 1, 'points' => 1, 'deleted_at' => null],
            ['id' => 2, 'points' => 1, 'deleted_at' => '2026-01-01 00:00:00'],
        ]);

        $this->assertSame([1], $result['correct_option_ids']);
        $this->assertSame(1, $result['correct_option_count']);
    }

    public function test_answer_key_state_is_always_pending_regardless_of_count(): void
    {
        $zero = (new AnswerKeyDeriver)->derive([['id' => 1, 'points' => 0, 'deleted_at' => null]]);
        $one = (new AnswerKeyDeriver)->derive([['id' => 1, 'points' => 1, 'deleted_at' => null]]);
        $many = (new AnswerKeyDeriver)->derive([
            ['id' => 1, 'points' => 1, 'deleted_at' => null],
            ['id' => 2, 'points' => 1, 'deleted_at' => null],
        ]);

        $this->assertSame('pending', $zero['answer_key_state']);
        $this->assertSame('pending', $one['answer_key_state']);
        $this->assertSame('pending', $many['answer_key_state']);
    }
}

<?php

namespace App\Support\Health;

use InvalidArgumentException;

final readonly class CheckResult
{
    public const MUST_SUCCEED = 'must_succeed';

    public const MUST_BE_REFUSED = 'must_be_refused';

    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const SKIPPED = 'skipped';

    public function __construct(
        public int $number,
        public string $name,
        public string $target,
        public string $expectation,
        public string $outcome,
        public string $detail,
    ) {
        if (! in_array($expectation, [self::MUST_SUCCEED, self::MUST_BE_REFUSED], true)) {
            throw new InvalidArgumentException("Unknown health expectation [{$expectation}].");
        }

        if (! in_array($outcome, [self::PASS, self::FAIL, self::SKIPPED], true)) {
            throw new InvalidArgumentException("Unknown health outcome [{$outcome}].");
        }
    }

    /** @return array{number: int, name: string, target: string, expectation: string, outcome: string, detail: string} */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'name' => $this->name,
            'target' => $this->target,
            'expectation' => $this->expectation,
            'outcome' => $this->outcome,
            'detail' => $this->detail,
        ];
    }
}

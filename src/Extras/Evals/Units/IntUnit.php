<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;

class IntUnit implements Unit
{
    public function __construct(
        private string $name = '',
        private string $symbol = '',
    ) {}

    public function name(): string {
        return $this->name;
    }

    public function isValid(mixed $value): bool {
        return is_int($value);
    }

    public function toString(mixed $value, array $format = []): string {
        return $value . $this->symbol;
    }

    public function toFloat(mixed $value): float {
        return (float) $value;
    }
}
<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;

class FloatUnit implements Unit
{
    public function __construct(
        private string $name,
        private string $symbol,
        private int $precision = 2,
    ) {}

    public function name(): string {
        return $this->name;
    }

    public function isValid(mixed $value): bool {
        return is_float($value);
    }

    public function toString(mixed $value, array $format = []): string {
        return number_format($value, $this->precision) . $this->symbol;
    }

    public function toFloat(mixed $value): float {
        return (float) $value;
    }
}
<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;

class LossUnit implements Unit
{
    public function __construct(
        private string $name = 'loss',
        private int $precision = 4,
    ) {}

    public function name(): string {
        return $this->name;
    }

    public function isValid(mixed $value): bool {
        return is_numeric($value) && $value >= 0;
    }

    public function toString(mixed $value, array $format = []): string {
        $precision = $format['precision'] ?? $this->precision;
        return number_format($value, $precision);
    }

    public function toFloat(mixed $value): float {
        return (float) $value;
    }
}

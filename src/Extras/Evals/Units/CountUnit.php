<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;

class CountUnit implements Unit
{
    public function __construct(
        private string $name = 'count',
        private string $unit = 'unit',
    ) {}

    public function name(): string {
        return $this->name;
    }

    public function isValid(mixed $value): bool {
        return is_numeric($value) && $value >= 0;
    }

    public function toString(mixed $value, array $format = []): string {
        $unit = $format['unit'] ?? $this->unit;
        $suffix = $unit ?: " $unit(s)";
        return number_format($value) . $suffix;
    }

    public function toFloat(mixed $value): float {
        return (float) $value;
    }
}

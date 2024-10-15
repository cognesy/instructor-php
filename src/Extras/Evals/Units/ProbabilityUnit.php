<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;

class ProbabilityUnit implements Unit
{
    public function name(): string {
        return 'probability';
    }

    public function isValid(mixed $value): bool {
        return is_numeric($value) && $value >= 0 && $value <= 1;
    }

    public function toString(mixed $value, array $format = []): string {
        $displayAs = $format['as'] ?? 'percentage';
        return match($displayAs) {
            'percentage' => number_format($value * 100, $format['precision'] ?? 2) . '%',
            'decimal' => number_format($value, $format['precision'] ?? 4),
            default => throw new \InvalidArgumentException("Invalid displayAs format: $displayAs (expected 'percentage' or 'decimal')"),
        };
    }

    public function toFloat(mixed $value): float {
        return (float) $value;
    }
}

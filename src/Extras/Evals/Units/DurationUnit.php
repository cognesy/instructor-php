<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;

class DurationUnit implements Unit
{
    public function name(): string {
        return 'duration';
    }

    public function isValid(mixed $value): bool {
        return is_numeric($value);
    }

    public function toString(mixed $value, array $format = []): string {
        $displayAs = $format['as'] ?? 'seconds';
        // convert from microseconds
        return match($displayAs) {
            'hours' => number_format($value / 3600, $format['precision'] ?? 2) . ' hrs',
            'minutes' => number_format($value / 60, $format['precision'] ?? 2) . ' min',
            'seconds' => number_format($value, $format['precision'] ?? 2) . ' sec',
            'milliseconds' => number_format($value * 1000, $format['precision'] ?? 2) . ' ms',
            default => throw new \InvalidArgumentException("Invalid displayAs format: $displayAs (expected 'hours', 'minutes', 'seconds' or 'milliseconds')"),
        };
    }

    public function toFloat(mixed $value): float {
        return (float) $value;
    }
}

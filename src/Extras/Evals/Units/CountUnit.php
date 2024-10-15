<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;

class CountUnit implements Unit
{
    public function name(): string {
        return 'count';
    }

    public function isValid(mixed $value): bool {
        return is_numeric($value) && $value >= 0;
    }

    public function toString(mixed $value, array $format = []): string {
        $unit = $format['unit'] ?? '';
        $suffix = $unit ?: " $unit(s)";
        return number_format($value) . $suffix;
    }

    public function toFloat(mixed $value): float {
        return (float) $value;
    }
}

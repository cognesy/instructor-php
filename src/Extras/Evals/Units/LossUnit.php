<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;

class LossUnit implements Unit
{
    public function name(): string {
        return 'loss';
    }

    public function isValid(mixed $value): bool {
        return is_numeric($value) && $value >= 0;
    }

    public function toString(mixed $value, array $format = []): string {
        $precision = $format['precision'] ?? 4;
        return number_format($value, $precision);
    }

    public function toFloat(mixed $value): float {
        return (float) $value;
    }
}

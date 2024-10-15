<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;

class BooleanUnit implements Unit
{
    public function name(): string {
        return 'boolean';
    }

    public function isValid(mixed $value): bool {
        return is_bool($value);
    }

    public function toString(mixed $value, array $format = []): string {
        $trueValue = $format['true'] ?? 'yes';
        $falseValue = $format['false'] ?? 'no';
        return $value ? $trueValue : $falseValue;
    }

    public function toFloat(mixed $value): float {
        return $value ? 1.0 : 0.0;
    }
}

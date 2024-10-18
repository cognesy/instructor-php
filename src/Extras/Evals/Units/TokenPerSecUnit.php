<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

class TokenPerSecUnit
{
    public function name(): string {
        return 't/s';
    }

    public function isValid(mixed $value): bool {
        return is_float($value);
    }

    public function toString(mixed $value, array $format = []): string {
        $value = number_format($value, $format['decimals'] ?? 2);
        return $value . ' ' . $this->name();
    }

    public function toFloat(mixed $value): float {
        return (float) $value;
    }
}
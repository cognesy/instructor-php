<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;

class TokensUnit implements Unit
{
    public function name(): string {
        return 'tokens';
    }

    public function isValid(mixed $value): bool {
        return is_int($value);
    }

    public function toString(mixed $value, array $format = []): string {
        return $value . ' ' . $this->name();
    }

    public function toFloat(mixed $value): float {
        return (float) $value;
    }
}
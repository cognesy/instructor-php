<?php

namespace Cognesy\Instructor\Extras\Evals\Units;

use Cognesy\Instructor\Extras\Evals\Contracts\Unit;

class NoUnit implements Unit
{
    public function name(): string {
        return 'none';
    }

    public function isValid(mixed $value): bool {
        return true;
    }

    public function toString(mixed $value, array $format = []): string {
        return 'n/a';
    }

    public function toFloat(mixed $value): float {
        return 0;
    }
}
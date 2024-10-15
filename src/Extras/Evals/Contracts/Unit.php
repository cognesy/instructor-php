<?php

namespace Cognesy\Instructor\Extras\Evals\Contracts;

interface Unit
{
    // Returns the name of the unit
    public function name(): string;

    // Validates the value according to the unit's constraints
    public function isValid(mixed $value): bool;

    // Formats a value according to the unit
    public function toString(mixed $value, array $format = []): string;

    public function toFloat(mixed $value): float;
}

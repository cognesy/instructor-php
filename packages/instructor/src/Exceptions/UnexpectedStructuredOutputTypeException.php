<?php declare(strict_types=1);

namespace Cognesy\Instructor\Exceptions;

use UnexpectedValueException;

final class UnexpectedStructuredOutputTypeException extends UnexpectedValueException
{
    public function __construct(string $expected, mixed $actual)
    {
        parent::__construct(
            "Expected structured output result {$expected}, got " . get_debug_type($actual) . '.',
        );
    }
}

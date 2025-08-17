<?php declare(strict_types=1);

namespace Cognesy\Instructor\Validation;

class ValidationError
{
    public function __construct(
        public string $field,
        public mixed $value,
        public string $message,
    ) {}

    public function __toString(): string
    {
        return "Validation error in field '{$this->field}' = '{$this->value}': {$this->message}";
    }
}
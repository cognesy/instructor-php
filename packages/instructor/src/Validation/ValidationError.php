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
        $value = $this->stringifyValue($this->value);
        return "Validation error in field '{$this->field}' = '{$value}': {$this->message}";
    }

    private function stringifyValue(mixed $value): string
    {
        return match (true) {
            $value instanceof \Stringable => (string) $value,
            is_string($value) => $value,
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_array($value) => $this->stringifyArray($value),
            default => get_debug_type($value),
        };
    }

    private function stringifyArray(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : 'array';
    }
}

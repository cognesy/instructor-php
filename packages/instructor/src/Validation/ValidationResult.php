<?php declare(strict_types=1);

namespace Cognesy\Instructor\Validation;

class ValidationResult
{
    private readonly bool $isValid;

    public function __construct(
        /** @var ValidationError[] */
        public array $errors = [],
        public string $message = '',
    ) {
        $this->isValid = count($errors) === 0;
        if ($this->isValid) {
            $this->message = '';
        }
    }

    /// FACTORY METHODS //////////////////////////////////////////////////////////////////////

    static public function valid(): ValidationResult {
        return new ValidationResult();
    }

    static public function invalid(string|array $errors, string $message = ''): ValidationResult {
        if (is_string($errors)) {
            $errors = [$errors];
        }
        if (count($errors) === 0) {
            throw new \InvalidArgumentException('Errors must be provided when creating an invalid ValidationResult');
        }
        return new ValidationResult($errors, $message);
    }

    static public function make(array $errors = [], string $message = ''): ValidationResult {
        return new ValidationResult($errors, $message);
    }

    static public function fieldError(string $field, mixed $value, string $message) : ValidationResult {
        return new ValidationResult([new ValidationError($field, $value, $message)], "Incorrect field value");
    }

    static public function merge(array $validationResults, string $message = ''): ValidationResult {
        $errors = [];
        $hasErrors = false;
        foreach ($validationResults as $result) {
            if (empty($result) || $result->isValid) {
                continue;
            }
            $hasErrors = true;
            $errors[] = $result->errors;
        }
        return match ($hasErrors) {
            true => self::invalid(array_merge(...$errors), $message ?: "Data validation failed"),
            false => self::valid(),
        };
    }

    /// CONVENIENCE METHODS //////////////////////////////////////////////////////////////////

    public function isValid(): bool {
        return $this->isValid;
    }

    public function isInvalid(): bool {
        return !$this->isValid;
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getErrorMessage() : string {
        if ($this->isValid) {
            return '';
        }
        $output = [$this->message];
        foreach ($this->errors as $error) {
            $output[] = " - " . ((string) $error);
        }
        return implode("\n", $output);
    }

    public function toArray(): array {
        return [
            'isValid' => $this->isValid,
            'errors' => $this->errors,
            'message' => $this->message,
        ];
    }
}
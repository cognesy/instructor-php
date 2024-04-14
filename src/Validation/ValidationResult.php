<?php

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

    static public function invalid(array $errors, string $message = ''): ValidationResult {
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
}
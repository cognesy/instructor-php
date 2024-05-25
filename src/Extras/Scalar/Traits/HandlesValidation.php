<?php

namespace Cognesy\Instructor\Extras\Scalar\Traits;

use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;

trait HandlesValidation
{
    /**
     * Validate scalar value
     */
    public function validate() : ValidationResult {
        $errors = [];
        if ($this->required && $this->value === null) {
            $errors[] = new ValidationError(
                $this->name,
                $this->value,
                "Value '{$this->name}' is required");
        }
        if (!empty($this->options) && !in_array($this->value, $this->options)) {
            $errors[] = new ValidationError(
                $this->name,
                $this->value,
                "Value '{$this->name}' must be one of: " . implode(", ", $this->options));
        }
        return ValidationResult::make($errors, "Validation failed for '{$this->name}'");
    }
}
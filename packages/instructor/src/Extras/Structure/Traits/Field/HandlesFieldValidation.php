<?php
namespace Cognesy\Instructor\Extras\Structure\Traits\Field;

use Cognesy\Instructor\Validation\ValidationResult;

trait HandlesFieldValidation
{
    private $validator;

    /**
     * Defines a simple, inline validator for the field - the callback has to return true/false
     *
     * @param callable $validator
     * @return $this
     */
    public function validIf(callable $validator, string $error = '') : self {
        $this->validator = function() use ($validator, $error) {
            $result = $validator($this->get());
            if ($result === false) {
                return ValidationResult::fieldError($this->name(), $this->get(), $error ?: "Invalid field value");
            }
            return ValidationResult::valid();
        };
        return $this;
    }

    /**
     * Defines validator for the field - the callback has to return ValidationResult
     *
     * @param callable $validator
     * @return $this
     */
    public function validator(callable $validator) : self {
        $this->validator = $validator;
        return $this;
    }

    /**
     * Validates the field value
     *
     * @return \Cognesy\Instructor\Validation\ValidationResult
     */
    public function validate() : ValidationResult {
        if ($this->validator === null) {
            return ValidationResult::valid();
        }
        return ($this->validator)($this->get());
    }
}
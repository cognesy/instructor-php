<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Traits\Structure;

use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Validation\ValidationResult;

trait HandlesValidation
{
    private $validator;

    public function validate(): ValidationResult {
        $failedValidations = [];
        // call validator if defined
        if ($this->hasValidator()) {
            $result = ($this->validator)($this);
            if ($result->isInvalid()) {
                return $result;
            }
        }

        // validate individual fields
        $invalidFields = [];
        foreach ($this->fields() as $name => $field) {
            if ($field instanceof CanValidateSelf) {
                $result = $field->validate();
                if ($result->isInvalid()) {
                    $failedValidations[] = $result;
                    $invalidFields[] = $name;
                }
            }
        }
        $message = "Validation failed for fields: " . implode(', ', $invalidFields);
        return ValidationResult::merge($failedValidations, $message);
    }

    public function validator(callable $validator) : self {
        $this->validator = $validator;
        return $this;
    }

    public function hasValidator() : bool {
        return $this->validator !== null;
    }
}
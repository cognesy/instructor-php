<?php

namespace Cognesy\Instructor\Validation\Contracts;

use Cognesy\Instructor\Validation\ValidationResult;

/**
 * Class can validate scalar values - used by validator classes
 */
interface CanValidateValue
{
    /**
     * Validate provided value
     * @param mixed $dataValue
     * @return ValidationResult
     */
    public function validate(mixed $dataValue): ValidationResult;
}
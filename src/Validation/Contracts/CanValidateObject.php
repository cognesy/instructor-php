<?php

namespace Cognesy\Instructor\Validation\Contracts;

use Cognesy\Instructor\Validation\ValidationResult;

/**
 * Class can validate other objects - used by validator classes
 */
interface CanValidateObject
{
    /**
     * Validate provided object
     * @param object $dataObject
     * @return ValidationResult
     */
    public function validate(object $dataObject) : ValidationResult;
}

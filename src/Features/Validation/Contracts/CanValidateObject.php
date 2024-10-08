<?php

namespace Cognesy\Instructor\Features\Validation\Contracts;

use Cognesy\Instructor\Features\Validation\ValidationResult;

/**
 * Class can validate other objects - used by validator classes
 */
interface CanValidateObject
{
    /**
     * Validate provided object
     * @param object $dataObject
     * @return \Cognesy\Instructor\Features\Validation\ValidationResult
     */
    public function validate(object $dataObject) : ValidationResult;
}

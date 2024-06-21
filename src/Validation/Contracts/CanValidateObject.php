<?php

namespace Cognesy\Instructor\Validation\Contracts;

use Cognesy\Instructor\Validation\ValidationResult;

/**
 * Class can validate other objects - used by validator classes
 */
interface CanValidateObject
{
    /**
     * Validate response object
     * @param object $dataObject
     * @return array
     */
    public function validate(object $dataObject) : ?ValidationResult;
}

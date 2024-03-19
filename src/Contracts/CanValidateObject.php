<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ValidationResult;

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
    public function validate(object $dataObject) : ValidationResult;
}

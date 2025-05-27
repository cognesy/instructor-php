<?php

namespace Cognesy\Instructor\Validation\Contracts;

use Cognesy\Instructor\Validation\ValidationResult;

/**
 * Response model can validate itself.
 */
interface CanValidateSelf
{
    /**
     * Validates itself and returns result of validation.
     */
    public function validate(): ValidationResult;
}

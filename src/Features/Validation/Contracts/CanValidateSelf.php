<?php

namespace Cognesy\Instructor\Features\Validation\Contracts;

use Cognesy\Instructor\Features\Validation\ValidationResult;

/**
 * Response model can validate itself.
 */
interface CanValidateSelf
{
    /**
     * Validate self
     * @return ValidationResult
     */
    public function validate(): ValidationResult;
}

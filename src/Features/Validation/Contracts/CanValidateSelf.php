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
     * @return \Cognesy\Instructor\Features\Validation\ValidationResult
     */
    public function validate(): ValidationResult;
}

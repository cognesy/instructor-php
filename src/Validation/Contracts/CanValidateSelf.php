<?php

namespace Cognesy\Instructor\Validation\Contracts;

use Cognesy\Instructor\Validation\ValidationResult;

/**
 * Response model can validate itself.
 */
interface CanValidateSelf
{
    public function validate(): ValidationResult;
}

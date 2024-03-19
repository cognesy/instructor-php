<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ValidationResult;

/**
 * Response model can validate itself.
 */
interface CanValidateSelf
{
    public function validate(): ValidationResult;
}

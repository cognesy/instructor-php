<?php

namespace Cognesy\Instructor\Contracts;

/**
 * Response model can validate itself.
 */
interface CanValidateSelf
{
    public function validate(): array;
}

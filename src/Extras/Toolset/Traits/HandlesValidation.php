<?php

namespace Cognesy\Instructor\Extras\Toolset\Traits;

use Cognesy\Instructor\Validation\ValidationResult;

trait HandlesValidation
{
    public function validate(): ValidationResult {
        return $this->call->validate();
    }
}
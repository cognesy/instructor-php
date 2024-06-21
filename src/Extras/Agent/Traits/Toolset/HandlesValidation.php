<?php

namespace Cognesy\Instructor\Extras\Agent\Traits\Toolset;

use Cognesy\Instructor\Validation\ValidationResult;

trait HandlesValidation
{
    public function validate(): ValidationResult {
        return $this->call->validate();
    }
}
<?php

namespace Cognesy\Addons\FunctionCall\Traits;

use Cognesy\Instructor\Features\Validation\ValidationResult;

trait HandlesValidation
{
    public function validate(): ValidationResult {
        return $this->arguments->validate();
    }
}
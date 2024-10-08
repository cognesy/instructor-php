<?php

namespace Cognesy\Instructor\Extras\FunctionCall\Traits;

use Cognesy\Instructor\Features\Validation\ValidationResult;

trait HandlesValidation
{
    public function validate(): ValidationResult {
        return $this->arguments->validate();
    }
}
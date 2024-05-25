<?php

namespace Cognesy\Instructor\Extras\Call\Traits;

use Cognesy\Instructor\Validation\ValidationResult;

trait HandlesValidation
{
    public function validate(): ValidationResult {
        return $this->arguments->validate();
    }
}
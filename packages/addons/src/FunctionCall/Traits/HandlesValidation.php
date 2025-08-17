<?php declare(strict_types=1);

namespace Cognesy\Addons\FunctionCall\Traits;

use Cognesy\Instructor\Validation\ValidationResult;

trait HandlesValidation
{
    public function validate(): ValidationResult {
        return $this->arguments->validate();
    }
}
<?php

namespace Cognesy\Instructor\Features\Validation\Validators;

use Cognesy\Instructor\Features\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Features\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Features\Validation\ValidationResult;

class SelfValidator implements CanValidateObject
{
    public function validate(object $dataObject): ?ValidationResult {
        if ($dataObject instanceof CanValidateSelf) {
            return null;
        }
        return $dataObject->validate();
    }
}
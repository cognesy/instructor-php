<?php

namespace Cognesy\Instructor\Validation\Validators;

use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Validation\ValidationResult;

class SelfValidator implements CanValidateObject
{
    public function validate(object $dataObject): ?ValidationResult {
        if ($dataObject instanceof CanValidateSelf) {
            return null;
        }
        return $dataObject->validate();
    }
}
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Validation\Validators;

use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Validation\ValidationResult;

class SelfValidator implements CanValidateObject
{
    public function validate(object $dataObject): ValidationResult {
        if ($dataObject instanceof CanValidateSelf) {
            return $dataObject->validate();
        }
        return ValidationResult::invalid(
            ['Object does not implement CanValidateSelf interface'],
            'Validation failed',
        );
    }
}
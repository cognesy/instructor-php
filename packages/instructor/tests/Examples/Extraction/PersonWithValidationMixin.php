<?php

namespace Cognesy\Instructor\Tests\Examples\Extraction;

use Cognesy\Instructor\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;

class PersonWithValidationMixin
{
    use ValidationMixin;
    public string $name;
    public int $age;

    public function validate(): ValidationResult
    {
        $errors = [];
        if ($this->age < 18) {
            $errors[] = new ValidationError(
                'age',
                $this->age,
                'Person must be adult.',
            );
        }
        return new ValidationResult($errors, 'Person data is invalid.');
    }
}

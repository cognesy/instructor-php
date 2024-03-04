<?php

namespace Tests\Examples\Extraction;

use Cognesy\Instructor\Traits\ValidationMixin;

class PersonWithValidationMixin
{
    use ValidationMixin;
    public string $name;
    public int $age;

    public function validate(): array
    {
        $errors = [];
        if ($this->age < 18) {
            $errors[] = [
                'value' => $this->age,
                'path' => 'age',
                'message' => 'Person must be adult.',
            ];
        }
        return $errors;
    }
}

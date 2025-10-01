<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Sequence\Traits;

use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\ValidationResult;

trait HandlesValidation
{
    private CanValidateObject $validator;

    public function validate(): ValidationResult {
        $validationErrors = [];
        foreach ($this->list as $item) {
            $result = $this->validator->validate($item);
            if ($result->isInvalid()) {
                $validationErrors[] = $result->getErrors();
            }
        }
        return ValidationResult::make( array_merge(...$validationErrors), 'Sequence validation failed');
    }
}
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Sequence\Traits;

use Cognesy\Instructor\Validation\ValidationResult;

trait HandlesValidation
{
    private $validator;

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
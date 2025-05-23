<?php
namespace Cognesy\Instructor\Features\Validation\Validators;

use Cognesy\Instructor\Features\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Features\Validation\ValidationError;
use Cognesy\Instructor\Features\Validation\ValidationResult;
use Symfony\Component\Validator\Validation;

class SymfonyValidator implements CanValidateObject
{
    public function validate(object $dataObject) : ValidationResult {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $result = $validator->validate($dataObject);

        $errors = [];
        foreach ($result as $error) {
            $path = $error->getPropertyPath();
            $value = $error->getInvalidValue();
            $message = $error->getMessage();
            $errors[] = new ValidationError($path, $value, $message);
        }

        return ValidationResult::make($errors, 'Validation failed');
    }
}

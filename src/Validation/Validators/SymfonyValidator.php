<?php
namespace Cognesy\Instructor\Validation\Validators;

use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Validation;

class SymfonyValidator implements CanValidateObject
{
    public function validate(object $dataObject) : ValidationResult {
        $validator = Validation::createValidatorBuilder()
            ->addLoader(new AttributeLoader())
            ->getValidator();

        try {
            $result = $validator->validate($dataObject);
        } catch (\Exception $e) {
            return ValidationResult::make($errors, $e->getMessage());
        }
        
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

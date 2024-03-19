<?php
namespace Cognesy\Instructor\Validators\Symfony;

use Cognesy\Instructor\Contracts\CanValidateObject;
use Cognesy\Instructor\Data\ValidationError;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Validation;
use Cognesy\Instructor\Data\ValidationResult;

class Validator implements CanValidateObject
{
    public function validate(object $dataObject) : ValidationResult {
        $validator = Validation::createValidatorBuilder()
            ->addLoader(new AttributeLoader())
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
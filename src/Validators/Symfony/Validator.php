<?php
namespace Cognesy\Instructor\Validators\Symfony;

use Cognesy\Instructor\Contracts\CanValidateObject;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Validation;

class Validator implements CanValidateObject
{
    public function validate(object $dataObject) : array {
        $validator = Validation::createValidatorBuilder()
            ->addLoader(new AttributeLoader())
            ->getValidator();
        $result = $validator->validate($dataObject);
        $errors = [];
        foreach ($result as $error) {
            $path = $error->getPropertyPath();
            $value = $error->getInvalidValue();
            $message = $error->getMessage();
            $errors[] = "Error in {$path} = {$value} ({$message})";
        }
        return $errors;
    }
}
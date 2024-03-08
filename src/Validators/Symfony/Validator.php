<?php
namespace Cognesy\Instructor\Validators\Symfony;

use Cognesy\Instructor\Contracts\CanValidateResponse;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Validation;

class Validator implements CanValidateResponse
{
    public function validate(object $response) : array {
        $validator = Validation::createValidatorBuilder()
            ->addLoader(new AttributeLoader())
            ->getValidator();
        $result = $validator->validate($response);
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
<?php

namespace Cognesy\Instructor\Validators\Symfony;

use Cognesy\Instructor\Contracts\CanValidateObject;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Validation;

class Validator implements CanValidateObject
{
    public $errors;

    public function validate(object $object) : bool {
        $validator = Validation::createValidatorBuilder()
            ->addLoader(new AttributeLoader())
            ->getValidator();
        $this->errors = $validator->validate($object);
        return (count($this->errors) == 0);
    }

    public function errors() : string {
        $errors = [];
        $errors[] = "Invalid values found:";
        foreach ($this->errors as $error) {
            $path = $error->getPropertyPath();
            $value = $error->getInvalidValue();
            $message = $error->getMessage();
            $errors[] = "   * parameter: {$path} = {$value} ({$message})";
        }
        return implode("\n", $errors);
    }
}
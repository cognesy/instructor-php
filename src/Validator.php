<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\CanValidate;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Validation;

class Validator implements CanValidate
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
            $errors[] = "   * parameter: " . $error->getPropertyPath() . ' = ' . $error->getInvalidValue() . " (" . $error->getMessage() . ")";
        }
        return implode("\n", $errors);
    }
}
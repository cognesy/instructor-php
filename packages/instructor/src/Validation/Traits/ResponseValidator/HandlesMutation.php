<?php

namespace Cognesy\Instructor\Validation\Traits\ResponseValidator;

use Cognesy\Instructor\Validation\Contracts\CanValidateObject;

trait HandlesMutation
{
    /** @param CanValidateObject[] $validators */
    public function appendValidators(array $validators) : self {
        $this->validators = array_merge($this->validators, $validators);
        return $this;
    }

    /** @param CanValidateObject[] $validators */
    public function setValidators(array $validators) : self {
        $this->validators = $validators;
        return $this;
    }
}
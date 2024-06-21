<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Validation\Contracts\CanValidateObject;

trait HandlesOverrides
{
    public function withValidator(CanValidateObject $validator) : static {
        $this->config->override([CanValidateObject::class => $validator]);
        return $this;
    }

    public function withValidators(array $validators) : static {
        $this->config->override(['instructor.validators' => $validators]);
        return $this;
    }
}

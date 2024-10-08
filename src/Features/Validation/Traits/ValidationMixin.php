<?php

namespace Cognesy\Instructor\Features\Validation\Traits;

use Cognesy\Instructor\Features\Validation\ValidationResult;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

trait ValidationMixin
{
    #[Assert\Callback]
    public function validateCallback(ExecutionContextInterface $context, mixed $payload) {
        $result = $this->validate();
        foreach ($result->getErrors() as $error) {
            $context->buildViolation($error->message)
                ->atPath($error->field)
                ->setInvalidValue($error->value)
                ->addViolation();
        }
    }

    abstract public function validate() : ValidationResult;
}

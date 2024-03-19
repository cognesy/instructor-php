<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Data\ValidationResult;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;

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

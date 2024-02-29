<?php

namespace Cognesy\Instructor\Traits;

use Exception;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;

trait ValidationMixin
{
    #[Assert\Callback]
    public function validateCallback(ExecutionContextInterface $context, mixed $payload) {
        $errors = $this->validate();
        foreach ($errors as $error) {
            $message = $error['message'] ?? throw new Exception("Error message not provided in validate() results.");
            $path = $error['path'] ?? throw new Exception("Error path not provided in validate() results.");
            $value = $error['value'] ?? throw new Exception("Error value not provided in validate() results.");
            $context->buildViolation($message)
                ->atPath($path)
                ->setInvalidValue($value)
                ->addViolation();
        }
    }

    /**
     * Validates the object
     *
     * Returns array of errors with following fields:
     *
     * $errors = [
     *    [
     *       'value' => '', // invalid value
     *       'path' => '', // path to the field with invalid value
     *       'message' => '', // error message
     *    ],
     *    ...
     * ];
     *
     * @return array
     */
    abstract public function validate() : array;
}

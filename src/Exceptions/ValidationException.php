<?php
namespace Cognesy\Instructor\Exceptions;

use Exception;

class ValidationException extends Exception
{
    public function __construct(
        public $message,
        public array $errors
    ) {
        parent::__construct($message);
    }

    public function __toString() : string {
        return json_encode([
            'message' => $this->message,
            'errors' => $this->errors
        ]);
    }
}

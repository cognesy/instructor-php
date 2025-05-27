<?php
namespace Cognesy\Instructor\Validation\Exceptions;

use Cognesy\Utils\Json\Json;
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
        return Json::encode([
            'message' => $this->message,
            'errors' => $this->errors
        ]);
    }
}

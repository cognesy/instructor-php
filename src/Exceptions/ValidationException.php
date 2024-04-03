<?php
namespace Cognesy\Instructor\Exceptions;

use Cognesy\Instructor\Utils\Json;
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

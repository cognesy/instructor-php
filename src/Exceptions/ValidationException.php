<?php
namespace Cognesy\Instructor\Exceptions;

use Exception;

class ValidationException extends Exception
{
    public function __construct(public $message) {
        parent::__construct($message);
    }
}

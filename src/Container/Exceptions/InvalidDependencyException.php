<?php

namespace Cognesy\Instructor\Container\Exceptions;

use Exception;

class InvalidDependencyException extends Exception
{
    public function __construct(string $message = 'Invalid dependency', int $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
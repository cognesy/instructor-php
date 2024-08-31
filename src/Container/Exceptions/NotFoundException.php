<?php

namespace Cognesy\Instructor\Container\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    public function __construct(string $message = 'Component not found', int $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
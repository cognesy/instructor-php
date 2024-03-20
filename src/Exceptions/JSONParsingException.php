<?php
namespace Cognesy\Instructor\Exceptions;

use Exception;

class JSONParsingException extends Exception
{
    public function __construct(
        public $message = "JSON parsing failed",
        public string $json = '',
    ) {
        parent::__construct($message);
    }
}
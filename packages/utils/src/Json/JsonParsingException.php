<?php declare(strict_types=1);

namespace Cognesy\Utils\Json;

use Exception;

class JsonParsingException extends Exception
{
    public function __construct(
        public $message = "JSON parsing failed",
        public string $json = '',
    ) {
        parent::__construct($message);
    }
}
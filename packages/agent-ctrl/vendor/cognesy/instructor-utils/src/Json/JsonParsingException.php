<?php declare(strict_types=1);

namespace Cognesy\Utils\Json;

use Exception;

class JsonParsingException extends Exception
{
    public string $json;

    public function __construct(
        string $message = "JSON parsing failed",
        string $json = '',
    ) {
        parent::__construct($message);
        $this->json = $json;
    }
}
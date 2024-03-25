<?php
namespace Cognesy\Instructor\ApiClient\OpenRouter\JsonCompletion;

use Cognesy\Instructor\ApiClient\PartialJsonResponse;

class PartialJsonCompletionResponse extends PartialJsonResponse
{
    static public function fromPartialResponse(string $partialData) : self {
        return new self($partialData, null, []);
    }
}
<?php
namespace Cognesy\Instructor\ApiClient\OpenRouter\ToolsCall;

use Cognesy\Instructor\ApiClient\PartialJsonResponse;

class PartialToolsCallResponse extends PartialJsonResponse
{
    static public function fromPartialResponse(string $partialData) : self {
        return new self($partialData, null, []);
    }
}
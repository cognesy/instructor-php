<?php
namespace Cognesy\Instructor\ApiClient\OpenAI\ToolsCall;

use Cognesy\Instructor\ApiClient\PartialJsonResponse;

class PartialToolsCallResponse extends PartialJsonResponse
{
    static public function fromPartialResponse(string $partialData) : self {
        return new self($partialData, null, []);
    }
}
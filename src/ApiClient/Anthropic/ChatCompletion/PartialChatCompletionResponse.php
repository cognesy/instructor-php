<?php
namespace Cognesy\Instructor\ApiClient\Anthropic\ChatCompletion;

use Cognesy\Instructor\ApiClient\PartialJsonResponse;

class PartialChatCompletionResponse extends PartialJsonResponse
{
    static public function fromPartialResponse(string $partialData): self {
        return new self($partialData, [], []);
    }
}

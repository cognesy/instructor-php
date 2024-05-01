<?php

namespace Cognesy\Instructor\Clients\OpenAI\ChatCompletion;

use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class PartialChatCompletionResponse extends PartialApiResponse
{
    static public function fromPartialResponse(string $partialData) : self {
        $decoded = Json::parse($partialData, default: []);
        $delta = $decoded['choices'][0]['delta']['content'] ?? '';
        return new self($delta, $decoded);
    }
}

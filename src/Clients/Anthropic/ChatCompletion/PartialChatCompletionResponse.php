<?php
namespace Cognesy\Instructor\Clients\Anthropic\ChatCompletion;

use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class PartialChatCompletionResponse extends PartialApiResponse
{
    static public function fromPartialResponse(string $partialData): self {
        $decoded = Json::parse($partialData, default: []);
        $delta = $decoded['delta']['text'] ?? '';
        return new self($delta, $decoded);
    }
}

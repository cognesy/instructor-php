<?php
namespace Cognesy\Instructor\Clients\Anthropic\JsonCompletion;

use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;

class PartialJsonCompletionResponse extends PartialApiResponse
{
    static public function fromPartialResponse(string $partialData) : self {
        $decoded = Json::parse($partialData, default: []);
        $delta = $decoded['delta']['text'] ?? '';
        return new self($delta, $decoded);
    }
}
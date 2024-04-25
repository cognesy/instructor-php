<?php
namespace Cognesy\Instructor\Clients\Anthropic\ToolsCall;

use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;

class PartialToolsCallResponse extends PartialApiResponse
{
    static public function fromPartialResponse(string $partialData) : self {
        $decoded = Json::parse($partialData, default: []);
        $argumentsJson = $decoded['delta']['text'] ?? '';
        return new self($argumentsJson, $decoded);
    }
}
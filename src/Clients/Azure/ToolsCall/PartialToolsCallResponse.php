<?php
namespace Cognesy\Instructor\Clients\Azure\ToolsCall;

use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;

class PartialToolsCallResponse extends PartialApiResponse
{
    static public function fromPartialResponse(string $partialData) : self {
        $decoded = Json::parse($partialData, default: []);
        $functionName = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '';
        $argumentsJson = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        return new self($argumentsJson, $decoded, $functionName);
    }
}
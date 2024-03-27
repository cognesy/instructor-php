<?php
namespace Cognesy\Instructor\Clients\TogetherAI\ToolsCall;

use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;

class PartialToolsCallResponse extends PartialApiResponse
{
    static public function fromPartialResponse(string $partialData) : self {
        $decoded = json_decode($partialData, true);
        $decoded = (empty($decoded)) ? [] : $decoded;
        $functionName = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '';
        $argumentsJson = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        return new self($argumentsJson, $decoded, $functionName);
    }
}
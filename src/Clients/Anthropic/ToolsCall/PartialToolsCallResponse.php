<?php
namespace Cognesy\Instructor\Clients\Anthropic\ToolsCall;

use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;

class PartialToolsCallResponse extends PartialApiResponse
{
    static public function fromPartialResponse(string $partialData) : self {
        $decoded = json_decode($partialData, true);
        $decoded = (empty($decoded)) ? [] : $decoded;
        $functionName = '';
        $argumentsJson = $decoded['delta']['text'] ?? '';
        return new self($argumentsJson, $decoded, $functionName);
    }
}
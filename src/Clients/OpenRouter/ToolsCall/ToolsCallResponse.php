<?php
namespace Cognesy\Instructor\Clients\OpenRouter\ToolsCall;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

class ToolsCallResponse extends ApiResponse
{
    public static function fromResponse(Response $response): self {
        $decoded = Json::parse($response->body());
        $content = $decoded['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
        $functionName = $decoded['choices'][0]['message']['tool_calls'][0]['function']['name'] ?? '';
        $finishReason = $decoded['choices'][0]['finish_reason'] ?? '';
        return new self(
            content: $content,
            responseData: $decoded,
            functionName: $functionName,
            finishReason: $finishReason,
            toolCalls: null
        );
    }
}
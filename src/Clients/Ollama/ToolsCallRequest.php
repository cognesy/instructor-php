<?php
namespace Cognesy\Instructor\Clients\Ollama;

use Cognesy\Instructor\ApiClient\Requests\ApiToolsCallRequest;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

class ToolsCallRequest extends ApiToolsCallRequest
{
    protected string $defaultEndpoint = '/chat/completions';

    public function toApiResponse(Response $response): ApiResponse {
        $decoded = Json::parse($response);
        $content = $decoded['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
        $toolName = $decoded['choices'][0]['message']['tool_calls'][0]['function']['name'] ?? '';
        $finishReason = $decoded['choices'][0]['finish_reason'] ?? '';
        return new ApiResponse(
            content: $content,
            responseData: $decoded,
            toolName: $toolName,
            finishReason: $finishReason,
            toolCalls: null
        );
    }

    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
        $decoded = Json::parse($partialData, default: []);
        $toolName = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '';
        $argumentsJson = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        return new PartialApiResponse($argumentsJson, $decoded, $toolName);
    }
}

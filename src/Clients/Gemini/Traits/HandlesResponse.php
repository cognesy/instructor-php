<?php
namespace Cognesy\Instructor\Clients\Gemini\Traits;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json\Json;
use Saloon\Http\Response;

trait HandlesResponse
{
    public function toApiResponse(Response $response): ApiResponse {
        $decoded = Json::parse($response->body());
        return new ApiResponse(
            content: $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '',
            responseData: $decoded,
            toolName: '',
            finishReason: $decoded['candidates'][0]['finishReason'] ?? '',
            toolCalls: null,
            inputTokens: $decoded['usageMetadata']['promptTokenCount'] ?? 0,
            outputTokens: $decoded['usageMetadata']['candidatesTokenCount'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
        $decoded = Json::parse($partialData, default: []);
        return new PartialApiResponse(
            delta: $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '',
            responseData: $decoded,
            toolName: $decoded['tool_calls'][0]['name'] ?? '',
            finishReason: $decoded['candidates'][0]['finishReason'] ?? '',
            inputTokens: $decoded['usageMetadata']['promptTokenCount'] ?? 0,
            outputTokens: $decoded['usageMetadata']['candidatesTokenCount'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }
}

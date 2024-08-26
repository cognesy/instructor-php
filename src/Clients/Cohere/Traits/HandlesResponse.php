<?php
namespace Cognesy\Instructor\Clients\Cohere\Traits;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json\Json;
use Saloon\Http\Response;

trait HandlesResponse
{
    public function toApiResponse(Response $response): ApiResponse {
        $decoded = Json::parse($response->body());
        return new ApiResponse(
            content: $decoded['text'] ?? '',
            responseData: $decoded,
            toolName: '',
            finishReason: $decoded['finish_reason'] ?? '',
            toolCalls: null,
            inputTokens: $decoded['meta']['tokens']['input_tokens'] ?? 0,
            outputTokens: $decoded['meta']['tokens']['output_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
        $decoded = Json::parse($partialData, default: []);
        return new PartialApiResponse(
            delta: $decoded['text'] ?? $decoded['tool_calls'][0]['parameters'] ?? '',
            responseData: $decoded,
            toolName: $decoded['tool_calls'][0]['name'] ?? '',
            finishReason: $decoded['finish_reason'] ?? '',
            inputTokens: $decoded['message']['usage']['input_tokens'] ?? $decoded['usage']['input_tokens'] ?? 0,
            outputTokens: $decoded['message']['usage']['output_tokens'] ?? $decoded['usage']['input_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }
}
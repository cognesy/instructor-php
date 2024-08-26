<?php
namespace Cognesy\Instructor\Clients\Anthropic\Traits;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json\Json;
use Saloon\Http\Response;

trait HandlesResponse
{
    public function toApiResponse(Response $response): ApiResponse {
        $decoded = Json::parse($response->body());
        return new ApiResponse(
            content: $decoded['content'][0]['text'] ?? Json::encode($decoded['content'][0]['input']) ?? '',
            responseData: $decoded,
            toolName: $decoded['content'][0]['name'] ?? '',
            finishReason: $decoded['stop_reason'] ?? '',
            toolCalls: null,
            inputTokens: $decoded['usage']['input_tokens'] ?? 0,
            outputTokens: $decoded['usage']['output_tokens'] ?? 0,
            cacheCreationTokens: $decoded['usage']['cache_creation_input_tokens'] ?? 0,
            cacheReadTokens: $decoded['usage']['cache_read_input_tokens'] ?? 0,
        );
    }

    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
        $decoded = Json::parse($partialData, default: []);
        $finishReason = $decoded['message']['stop_reason'] ?? $decoded['message']['stop_reason'] ?? '';
        return new PartialApiResponse(
            delta: $decoded['delta']['text'] ?? $decoded['delta']['partial_json'] ?? '',
            responseData: $decoded,
            toolName: $decoded['content_block']['name'] ?? '',
            finishReason: $finishReason,
            inputTokens: $decoded['message']['usage']['input_tokens'] ?? $decoded['usage']['input_tokens'] ?? 0,
            outputTokens: $decoded['message']['usage']['output_tokens'] ?? $decoded['usage']['output_tokens'] ?? 0,
            cacheCreationTokens: $decoded['message']['usage']['cache_creation_input_tokens'] ?? $decoded['usage']['cache_creation_input_tokens'] ?? 0,
            cacheReadTokens: $decoded['message']['usage']['cache_read_input_tokens'] ?? $decoded['usage']['cache_read_input_tokens'] ?? 0,
        );
    }
}

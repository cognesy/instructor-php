<?php
namespace Cognesy\Instructor\Clients\Gemini\Traits;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

trait HandlesResponse
{
    public function toApiResponse(Response $response): ApiResponse {
        $decoded = Json::parse($response->body());
        $content = $decoded['text'] ?? '';
        $finishReason = $decoded['finish_reason'] ?? '';
        $inputTokens = $decoded['meta']['tokens']['input_tokens'] ?? 0;
        $outputTokens = $decoded['meta']['tokens']['output_tokens'] ?? 0;
        return new ApiResponse(
            content: $content,
            responseData: $decoded,
            toolName: '',
            finishReason: $finishReason,
            toolCalls: null,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
        $decoded = Json::parse($partialData, default: []);
        $delta = $decoded['text'] ?? $decoded['tool_calls'][0]['parameters'] ?? '';
        $inputTokens = $decoded['message']['usage']['input_tokens'] ?? $decoded['usage']['input_tokens'] ?? 0;
        $outputTokens = $decoded['message']['usage']['output_tokens'] ?? $decoded['usage']['input_tokens'] ?? 0;
        $finishReason = $decoded['finish_reason'] ?? '';
        return new PartialApiResponse(
            delta: $delta,
            responseData: $decoded,
            toolName: $decoded['tool_calls'][0]['name'] ?? '',
            finishReason: $finishReason,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }
}
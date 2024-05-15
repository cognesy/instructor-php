<?php

namespace Cognesy\Instructor\Clients\Anthropic\Traits;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

trait HandlesResponse
{
    public function toApiResponse(Response $response): ApiResponse {
        $decoded = Json::parse($response->body());
        $content = $decoded['content'][0]['text'] ?? '';
        $finishReason = $decoded['stop_reason'] ?? '';
        $inputTokens = $decoded['delta']['input_tokens'] ?? 0;
        $outputTokens = $decoded['delta']['output_tokens'] ?? 0;
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
        $delta = $decoded['delta']['text'] ?? '';
        $inputTokens = $decoded['message']['usage']['input_tokens'] ?? $decoded['usage']['input_tokens'] ?? 0;
        $outputTokens = $decoded['message']['usage']['output_tokens'] ?? $decoded['usage']['input_tokens'] ?? 0;
        $finishReason = $decoded['message']['stop_reason'] ?? $decoded['message']['stop_reason'] ?? '';
        return new PartialApiResponse(
            delta: $delta,
            responseData: $decoded,
            toolName: '',
            finishReason: $finishReason,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }
}
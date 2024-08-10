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
        $content = $decoded['content'][0]['text'] ?? Json::encode($decoded['content'][0]['input']) ?? '';
        $toolName = $decoded['content'][0]['name'] ?? '';
        $finishReason = $decoded['stop_reason'] ?? '';
        $inputTokens = $decoded['usage']['input_tokens'] ?? 0;
        $outputTokens = $decoded['usage']['output_tokens'] ?? 0;
        return new ApiResponse(
            content: $content,
            responseData: $decoded,
            toolName: $toolName,
            finishReason: $finishReason,
            toolCalls: null,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
        $decoded = Json::parse($partialData, default: []);
        $delta = $decoded['delta']['text'] ?? $decoded['delta']['partial_json'] ?? '';
        $toolName = $decoded['content_block']['name'] ?? '';
        $inputTokens = $decoded['message']['usage']['input_tokens'] ?? $decoded['usage']['input_tokens'] ?? 0;
        $outputTokens = $decoded['message']['usage']['output_tokens'] ?? $decoded['usage']['output_tokens'] ?? 0;
        $finishReason = $decoded['message']['stop_reason'] ?? $decoded['message']['stop_reason'] ?? '';
        return new PartialApiResponse(
            delta: $delta,
            responseData: $decoded,
            toolName: $toolName,
            finishReason: $finishReason,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }
}
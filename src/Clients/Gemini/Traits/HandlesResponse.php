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
        $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $finishReason = $decoded['candidates'][0]['finishReason'] ?? '';
        $inputTokens = $decoded['usageMetadata']['promptTokenCount'] ?? 0;
        $outputTokens = $decoded['usageMetadata']['candidatesTokenCount'] ?? 0;
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
dump($decoded);
        $delta = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $inputTokens = $decoded['usageMetadata']['promptTokenCount'] ?? 0;
        $outputTokens = $decoded['usageMetadata']['candidatesTokenCount'] ?? 0;
        $finishReason = $decoded['candidates'][0]['finishReason'] ?? '';
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

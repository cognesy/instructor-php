<?php

namespace Cognesy\Instructor\ApiClient\Requests\Traits;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json\Json;
use Exception;
use Saloon\Http\Response;

trait HandlesResponse
{
    public function toApiResponse(Response $response): ApiResponse {
        $decoded = Json::parse($response->body());
        if (empty($decoded)) {
            throw new Exception('Response body empty or does not contain correct JSON: ' . $response->body());
        }
        $finishReason = $decoded['choices'][0]['finish_reason'] ?? '';
        $toolName = $decoded['choices'][0]['message']['tool_calls'][0]['function']['name'] ?? '';
        $contentMsg = $decoded['choices'][0]['message']['content'] ?? '';
        $contentFnArgs = $decoded['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
        $content = match(true) {
            !empty($contentMsg) => $contentMsg,
            !empty($contentFnArgs) => $contentFnArgs,
            default => ''
        };
        $inputTokens = $decoded['usage']['prompt_tokens'] ?? 0;
        $outputTokens = $decoded['usage']['completion_tokens'] ?? 0;
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
        $finishReason = $decoded['choices'][0]['finish_reason'] ?? '';
        $toolName = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '';
        $deltaContent = $decoded['choices'][0]['delta']['content'] ?? '';
        $deltaFnArgs = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        $delta = match(true) {
            !empty($deltaContent) => $deltaContent,
            !empty($deltaFnArgs) => $deltaFnArgs,
            default => ''
        };
        $inputTokens = $decoded['usage']['prompt_tokens'] ?? 0;
        $outputTokens = $decoded['usage']['completion_tokens'] ?? 0;
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
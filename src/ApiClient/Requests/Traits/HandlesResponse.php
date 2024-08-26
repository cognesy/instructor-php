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
        return new ApiResponse(
            content: $this->getContent($decoded),
            responseData: $decoded,
            toolName: $decoded['choices'][0]['message']['tool_calls'][0]['function']['name'] ?? '',
            finishReason: $decoded['choices'][0]['finish_reason'] ?? '',
            toolCalls: null,
            inputTokens: $decoded['usage']['prompt_tokens'] ?? 0,
            outputTokens: $decoded['usage']['completion_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    public function toPartialApiResponse(string $partialData) : PartialApiResponse {
        $decoded = Json::parse($partialData, default: []);
        return new PartialApiResponse(
            delta: $this->getDelta($decoded),
            responseData: $decoded,
            toolName: $decoded['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '',
            finishReason: $decoded['choices'][0]['finish_reason'] ?? '',
            inputTokens: $decoded['usage']['prompt_tokens'] ?? 0,
            outputTokens: $decoded['usage']['completion_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    // INTERNAL ///////////////////////////////////////////////////////////////////////////////////////

    private function getContent(array $decoded): string {
        $contentMsg = $decoded['choices'][0]['message']['content'] ?? '';
        $contentFnArgs = $decoded['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            !empty($contentMsg) => $contentMsg,
            !empty($contentFnArgs) => $contentFnArgs,
            default => ''
        };
    }

    private function getDelta(array $decoded): string {
        $deltaContent = $decoded['choices'][0]['delta']['content'] ?? '';
        $deltaFnArgs = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            !empty($deltaContent) => $deltaContent,
            !empty($deltaFnArgs) => $deltaFnArgs,
            default => ''
        };
    }
}
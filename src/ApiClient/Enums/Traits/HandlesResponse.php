<?php

namespace Cognesy\Instructor\ApiClient\Enums\Traits;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json\Json;
use Exception;
use Saloon\Http\Response;

trait HandlesResponse
{
    public function toApiResponse(string $bodyData): ApiResponse {
        $response = Json::parse($bodyData);
        if (empty($response)) {
            throw new Exception('Response body empty or does not contain correct JSON: ' . $bodyData);
        }
        return match($this) {
            self::Anthropic => $this->toApiResponseAnthropic($response),
            self::Cohere => $this->toApiResponseCohere($response),
            self::Gemini => $this->toApiResponseGemini($response),
            default => $this->toApiResponseOpenAI($response),
        };
    }

    public function toPartialApiResponse(string $partialData): PartialApiResponse {
        return match($this) {
            self::Anthropic => $this->toPartialApiResponseAnthropic($partialData),
            self::Cohere => $this->toPartialApiResponseCohere($partialData),
            self::Gemini => $this->toPartialApiResponseGemini($partialData),
            default => $this->toPartialApiResponseOpenAI($partialData),
        };
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    protected function toApiResponseAnthropic(array $decoded): ApiResponse {
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

    protected function toPartialApiResponseAnthropic(string $partialData) : PartialApiResponse {
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

    protected function toApiResponseCohere(array $decoded): ApiResponse {
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

    protected function toPartialApiResponseCohere(string $partialData) : PartialApiResponse {
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

    protected function toApiResponseGemini(array $decoded): ApiResponse {
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

    protected function toPartialApiResponseGemini(string $partialData) : PartialApiResponse {
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

    public function toApiResponseOpenAI(array $decoded): ApiResponse {
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

    public function toPartialApiResponseOpenAI(string $partialData) : PartialApiResponse {
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
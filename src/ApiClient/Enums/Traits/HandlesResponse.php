<?php

namespace Cognesy\Instructor\ApiClient\Enums\Traits;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Utils\Json\Json;
use Exception;

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
        $response = Json::parse($partialData, default: []);
        return match($this) {
            self::Anthropic => $this->toPartialApiResponseAnthropic($response),
            self::Cohere => $this->toPartialApiResponseCohere($response),
            self::Gemini => $this->toPartialApiResponseGemini($response),
            default => $this->toPartialApiResponseOpenAI($response),
        };
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    protected function toApiResponseAnthropic(array $data): ApiResponse {
        return new ApiResponse(
            content: $data['content'][0]['text'] ?? Json::encode($data['content'][0]['input']) ?? '',
            responseData: $data,
            toolName: $data['content'][0]['name'] ?? '',
            finishReason: $data['stop_reason'] ?? '',
            toolCalls: null,
            inputTokens: $data['usage']['input_tokens'] ?? 0,
            outputTokens: $data['usage']['output_tokens'] ?? 0,
            cacheCreationTokens: $data['usage']['cache_creation_input_tokens'] ?? 0,
            cacheReadTokens: $data['usage']['cache_read_input_tokens'] ?? 0,
        );
    }

    protected function toPartialApiResponseAnthropic(array $data) : PartialApiResponse {
        $finishReason = $data['message']['stop_reason'] ?? $data['message']['stop_reason'] ?? '';
        return new PartialApiResponse(
            delta: $data['delta']['text'] ?? $data['delta']['partial_json'] ?? '',
            responseData: $data,
            toolName: $data['content_block']['name'] ?? '',
            finishReason: $finishReason,
            inputTokens: $data['message']['usage']['input_tokens'] ?? $data['usage']['input_tokens'] ?? 0,
            outputTokens: $data['message']['usage']['output_tokens'] ?? $data['usage']['output_tokens'] ?? 0,
            cacheCreationTokens: $data['message']['usage']['cache_creation_input_tokens'] ?? $data['usage']['cache_creation_input_tokens'] ?? 0,
            cacheReadTokens: $data['message']['usage']['cache_read_input_tokens'] ?? $data['usage']['cache_read_input_tokens'] ?? 0,
        );
    }

    protected function toApiResponseCohere(array $data): ApiResponse {
        return new ApiResponse(
            content: $data['text'] ?? '',
            responseData: $data,
            toolName: '',
            finishReason: $data['finish_reason'] ?? '',
            toolCalls: null,
            inputTokens: $data['meta']['tokens']['input_tokens'] ?? 0,
            outputTokens: $data['meta']['tokens']['output_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    protected function toPartialApiResponseCohere(array $data) : PartialApiResponse {
        return new PartialApiResponse(
            delta: $data['text'] ?? $data['tool_calls'][0]['parameters'] ?? '',
            responseData: $data,
            toolName: $data['tool_calls'][0]['name'] ?? '',
            finishReason: $data['finish_reason'] ?? '',
            inputTokens: $data['message']['usage']['input_tokens'] ?? $data['usage']['input_tokens'] ?? 0,
            outputTokens: $data['message']['usage']['output_tokens'] ?? $data['usage']['input_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    protected function toApiResponseGemini(array $data): ApiResponse {
        return new ApiResponse(
            content: $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
            responseData: $data,
            toolName: '',
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
            toolCalls: null,
            inputTokens: $data['usageMetadata']['promptTokenCount'] ?? 0,
            outputTokens: $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    protected function toPartialApiResponseGemini(array $data) : PartialApiResponse {
        return new PartialApiResponse(
            delta: $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
            responseData: $data,
            toolName: $data['tool_calls'][0]['name'] ?? '',
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
            inputTokens: $data['usageMetadata']['promptTokenCount'] ?? 0,
            outputTokens: $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    protected function toApiResponseOpenAI(array $data): ApiResponse {
        return new ApiResponse(
            content: $this->getContent($data),
            responseData: $data,
            toolName: $data['choices'][0]['message']['tool_calls'][0]['function']['name'] ?? '',
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            toolCalls: null,
            inputTokens: $data['usage']['prompt_tokens'] ?? 0,
            outputTokens: $data['usage']['completion_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    protected function toPartialApiResponseOpenAI(array $data) : PartialApiResponse {
        return new PartialApiResponse(
            delta: $this->getDelta($data),
            responseData: $data,
            toolName: $data['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '',
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            inputTokens: $data['usage']['prompt_tokens'] ?? 0,
            outputTokens: $data['usage']['completion_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    private function getContent(array $data): string {
        $contentMsg = $data['choices'][0]['message']['content'] ?? '';
        $contentFnArgs = $data['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            !empty($contentMsg) => $contentMsg,
            !empty($contentFnArgs) => $contentFnArgs,
            default => ''
        };
    }

    private function getDelta(array $data): string {
        $deltaContent = $data['choices'][0]['delta']['content'] ?? '';
        $deltaFnArgs = $data['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            !empty($deltaContent) => $deltaContent,
            !empty($deltaFnArgs) => $deltaFnArgs,
            default => ''
        };
    }
}

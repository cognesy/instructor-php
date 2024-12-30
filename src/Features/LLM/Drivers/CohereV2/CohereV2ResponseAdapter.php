<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\CohereV2;

use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAI\OpenAIResponseAdapter;

class CohereV2ResponseAdapter extends OpenAIResponseAdapter
{
    public function fromResponse(array $data): LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            finishReason: $data['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->makeUsage($data),
            responseData: $data,
        );
    }

    public function fromStreamResponse(array|null $data) : ?PartialLLMResponse {
        if (empty($data)) {
            return null;
        }
        return new PartialLLMResponse(
            contentDelta: $this->makeContentDelta($data),
            toolId: $data['delta']['message']['tool_calls']['function']['id'] ?? '',
            toolName: $data['delta']['message']['tool_calls']['function']['name'] ?? '',
            toolArgs: $data['delta']['message']['tool_calls']['function']['arguments'] ?? '',
            finishReason: $data['delta']['finish_reason'] ?? '',
            usage: $this->makeUsage($data),
            responseData: $data,
        );
    }

    public function fromStreamData(string $data): string|bool {
        if (!str_starts_with($data, 'data:')) {
            return '';
        }
        $data = trim(substr($data, 5));
        return match(true) {
            $data === '[DONE]' => false,
            default => $data,
        };
    }

    // OVERRIDES - HELPERS ///////////////////////////////////

    private function makeContent(array $data): string {
        $contentMsg = $data['message']['content'][0]['text'] ?? '';
        $contentFnArgs = $data['message']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            !empty($contentMsg) => $contentMsg,
            !empty($contentFnArgs) => $contentFnArgs,
            default => ''
        };
    }

    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromArray(array_map(
            callback: fn(array $call) => $this->makeToolCall($call),
            array: $data['message']['tool_calls'] ?? [],
        ));
    }

    private function makeToolCall(array $data) : ?ToolCall {
        if (empty($data)) {
            return null;
        }
        if (!isset($data['function'])) {
            return null;
        }
        if (!isset($data['id'])) {
            return null;
        }
        return ToolCall::fromArray($data['function'] ?? [])?->withId($data['id'] ?? '');
    }

    private function makeContentDelta(array $data): string {
        $deltaContent = match(true) {
            ([] !== ($data['delta']['message']['content'] ?? [])) => $this->normalizeContent($data['delta']['message']['content']),
            default => '',
        };
        $deltaFnArgs = $data['delta']['message']['tool_calls']['function']['arguments'] ?? '';
        return match(true) {
            '' !== $deltaContent => $deltaContent,
            '' !== $deltaFnArgs => $deltaFnArgs,
            default => ''
        };
    }

    private function normalizeContent(array|string $content) : string {
        return is_array($content) ? $content['text'] : $content;
    }

    private function makeUsage(array $data) : Usage {
        return new Usage(
            inputTokens: $data['usage']['billed_units']['input_tokens']
            ?? $data['delta']['usage']['billed_units']['input_tokens']
            ?? 0,
            outputTokens: $data['usage']['billed_units']['output_tokens']
            ?? $data['delta']['usage']['billed_units']['output_tokens']
            ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}

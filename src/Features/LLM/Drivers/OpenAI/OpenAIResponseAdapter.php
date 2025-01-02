<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\OpenAI;

use Cognesy\Instructor\Features\LLM\Contracts\CanMapUsage;
use Cognesy\Instructor\Features\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;

class OpenAIResponseAdapter implements ProviderResponseAdapter
{
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    public function fromResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $data,
        );
    }

    public function fromStreamResponse(array $data): ?PartialLLMResponse {
        if ($data === null || empty($data)) {
            return null;
        }
        return new PartialLLMResponse(
            contentDelta: $this->makeContentDelta($data),
            toolId: $this->makeToolId($data),
            toolName: $this->makeToolNameDelta($data),
            toolArgs: $this->makeToolArgsDelta($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            usage: $this->usageFormat->fromData($data),
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

    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromArray(array_map(
            callback: fn(array $call) => $this->makeToolCall($call),
            array: $data['choices'][0]['message']['tool_calls'] ?? []
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
        return ToolCall::fromArray($data['function'])?->withId($data['id']);
    }

    private function makeContent(array $data): string {
        $contentMsg = $data['choices'][0]['message']['content'] ?? '';
        $contentFnArgs = $data['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            !empty($contentMsg) => $contentMsg,
            !empty($contentFnArgs) => $contentFnArgs,
            default => ''
        };
    }

    private function makeContentDelta(array $data): string {
        $deltaContent = $data['choices'][0]['delta']['content'] ?? '';
        $deltaFnArgs = $data['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            ('' !== $deltaContent) => $deltaContent,
            ('' !== $deltaFnArgs) => $deltaFnArgs,
            default => ''
        };
    }

    private function makeToolId(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['id'] ?? '';
    }

    private function makeToolNameDelta(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '';
    }

    private function makeToolArgsDelta(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
    }
}
<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\Anthropic;

use Cognesy\Instructor\Features\LLM\Contracts\CanMapUsage;
use Cognesy\Instructor\Features\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;
use Cognesy\Instructor\Utils\Json\Json;

class AnthropicResponseAdapter implements ProviderResponseAdapter
{
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    public function fromResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            finishReason: $data['stop_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $data,
        );
    }

    public function fromStreamResponse(array $data): ?PartialLLMResponse {
        if (empty($data)) {
            return null;
        }
        return new PartialLLMResponse(
            contentDelta: $this->makeContentDelta($data),
            toolId: $data['content_block']['id'] ?? '',
            toolName: $data['content_block']['name'] ?? '',
            toolArgs: $data['delta']['partial_json'] ?? '',
            finishReason: $data['delta']['stop_reason'] ?? $data['message']['stop_reason'] ?? '',
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
            $data === 'event: message_stop' => false,
            default => $data,
        };
    }

    // INTERNAL //////////////////////////////////////////////

    private function makeContent(array $data) : string {
        return $data['content'][0]['text'] ?? Json::encode($data['content'][0]['input']) ?? '';
    }

    private function makeContentDelta(array $data) : string {
        return $data['delta']['text'] ?? $data['delta']['partial_json'] ?? '';
    }

    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromMapper(array_map(
            callback: fn(array $call) => $call,
            array: array_filter(
                array: $data['content'] ?? [],
                callback: fn($part) => 'tool_use' === ($part['type'] ?? ''))
        ), fn($call) => ToolCall::fromArray([
            'id' => $call['id'] ?? '',
            'name' => $call['name'] ?? '',
            'arguments' => $call['input'] ?? ''
        ]));
    }
}
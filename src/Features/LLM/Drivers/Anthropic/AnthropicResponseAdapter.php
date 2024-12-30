<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\Anthropic;

use Cognesy\Instructor\Features\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\Json\Json;

class AnthropicResponseAdapter implements ProviderResponseAdapter
{
    public function fromResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            finishReason: $data['stop_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->makeUsage($data),
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

    private function setCacheMarker(array $messages): array {
        $lastIndex = count($messages) - 1;
        $lastMessage = $messages[$lastIndex];

        if (is_array($lastMessage['content'])) {
            $subIndex = count($lastMessage['content']) - 1;
            $lastMessage['content'][$subIndex]['cache_control'] = ["type" => "ephemeral"];
        } else {
            $lastMessage['content'] = [[
                'type' => $lastMessage['type'] ?? 'text',
                'text' => $lastMessage['content'] ?? '',
                'cache_control' => ["type" => "ephemeral"],
            ]];
        }

        $messages[$lastIndex] = $lastMessage;
        return $messages;
    }

    private function makeUsage(array $data) : Usage {
        return new Usage(
            inputTokens: $data['usage']['input_tokens']
            ?? $data['message']['usage']['input_tokens']
            ?? 0,
            outputTokens: $data['usage']['output_tokens']
            ?? $data['message']['usage']['output_tokens']
            ?? 0,
            cacheWriteTokens: $data['usage']['cache_creation_input_tokens']
            ?? $data['message']['usage']['cache_creation_input_tokens']
            ?? 0,
            cacheReadTokens: $data['usage']['cache_read_input_tokens']
            ?? $data['message']['usage']['cache_read_input_tokens']
            ?? 0,
            reasoningTokens: 0,
        );
    }
}
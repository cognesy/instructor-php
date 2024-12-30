<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\CohereV1;

use Cognesy\Instructor\Features\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\Json\Json;

class CohereV1ResponseAdapter implements ProviderResponseAdapter
{
    public function fromResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            //: $this->map($data),
            finishReason: $data['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->makeUsage($data),
            responseData: $data,
        );
    }

    public function fromStreamResponse(array $data): ?PartialLLMResponse {
        return new PartialLLMResponse(
            contentDelta: $this->makeContentDelta($data),
            toolId: $this->makeToolId($data),
            toolName: $this->makeToolNameDelta($data),
            toolArgs: $this->makeToolArgsDelta($data),
            finishReason: $data['response']['finish_reason'] ?? $data['delta']['finish_reason'] ?? '',
            usage: $this->makeUsage($data),
            responseData: $data,
        );
    }

    public function fromStreamData(string $data): string|bool {
        $data = trim($data);
        return match(true) {
            $data === '[DONE]' => false,
            default => $data,
        };
    }

    // INTERNAL /////////////////////////////////////////////

    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromMapper(
            $data['tool_calls'] ?? [],
            fn($call) => ToolCall::fromArray(['name' => $call['name'] ?? '', 'arguments' => $call['parameters'] ?? ''])
        );
    }

    private function makeContent(array $data) : string {
        return ($data['text'] ?? '') . (!empty($data['tool_calls'])
                ? ("\n" . Json::encode($data['tool_calls']))
                : ''
            );
    }

    private function makeContentDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        return $data['tool_call_delta']['parameters'] ?? $data['text'] ?? '';
    }

    private function makeToolId(array $data) {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        return $data['tool_calls'][0]['id'] ?? '';
    }

    private function makeToolNameDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        return $data['tool_calls'][0]['name'] ?? '';
    }

    private function makeToolArgsDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        $toolArgs = $data['tool_calls'][0]['parameters'] ?? '';
        return ('' === $toolArgs) ? '' : Json::encode($toolArgs);
    }

    private function isStreamChunk(array $data) : bool {
        return in_array(($data['event_type'] ?? ''), ['text-generation', 'tool-calls-chunk']);
    }

    private function makeUsage(array $data) : Usage {
        return new Usage(
            inputTokens: $data['meta']['tokens']['input_tokens']
            ?? $data['response']['meta']['tokens']['input_tokens']
            ?? $data['delta']['tokens']['input_tokens']
            ?? 0,
            outputTokens: $data['meta']['tokens']['output_tokens']
            ?? $data['response']['meta']['tokens']['output_tokens']
            ?? $data['delta']['tokens']['input_tokens']
            ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}
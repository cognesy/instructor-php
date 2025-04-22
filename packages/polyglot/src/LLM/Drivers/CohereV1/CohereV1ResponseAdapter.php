<?php

namespace Cognesy\Polyglot\LLM\Drivers\CohereV1;

use Cognesy\Polyglot\LLM\Contracts\CanMapUsage;
use Cognesy\Polyglot\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\Data\ToolCall;
use Cognesy\Polyglot\LLM\Data\ToolCalls;
use Cognesy\Utils\Json\Json;

class CohereV1ResponseAdapter implements ProviderResponseAdapter
{
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    public function fromResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            //: $this->map($data),
            finishReason: $data['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->usageFormat->fromData($data),
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
            usage: $this->usageFormat->fromData($data),
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

    protected function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromMapper(
            $data['tool_calls'] ?? [],
            fn($call) => ToolCall::fromArray(['name' => $call['name'] ?? '', 'arguments' => $call['parameters'] ?? ''])
        );
    }

    protected function makeContent(array $data) : string {
        return ($data['text'] ?? '') . (!empty($data['tool_calls'])
                ? ("\n" . Json::encode($data['tool_calls']))
                : ''
            );
    }

    protected function makeContentDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        return $data['tool_call_delta']['parameters'] ?? $data['text'] ?? '';
    }

    protected function makeToolId(array $data) {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        return $data['tool_calls'][0]['id'] ?? '';
    }

    protected function makeToolNameDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        return $data['tool_calls'][0]['name'] ?? '';
    }

    protected function makeToolArgsDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        $toolArgs = $data['tool_calls'][0]['parameters'] ?? '';
        return ('' === $toolArgs) ? '' : Json::encode($toolArgs);
    }

    protected function isStreamChunk(array $data) : bool {
        return in_array(($data['event_type'] ?? ''), ['text-generation', 'tool-calls-chunk']);
    }
}
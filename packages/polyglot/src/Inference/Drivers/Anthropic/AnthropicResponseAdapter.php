<?php

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Utils\Json\Json;

class AnthropicResponseAdapter implements CanTranslateInferenceResponse
{
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        $responseBody = $response->body();
        //$responseBody = $this->normalizeUnknownValues($responseBody);
        $data = json_decode($responseBody, true);
        return new InferenceResponse(
            content: $this->makeContent($data),
            finishReason: $data['stop_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            reasoningContent: $this->makeReasoningContent($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $data,
        );
    }

    public function fromStreamResponse(string $eventBody): ?PartialInferenceResponse {
        //$eventBody = $this->normalizeUnknownValues($responseBody);
        $data = json_decode($eventBody, true);
        if (empty($data)) {
            return null;
        }
        return new PartialInferenceResponse(
            contentDelta: $this->makeContentDelta($data),
            reasoningContentDelta: $data['delta']['thinking_delta'] ?? '',
            toolId: $data['content_block']['id'] ?? '',
            toolName: $data['content_block']['name'] ?? '',
            toolArgs: $data['delta']['partial_json'] ?? '',
            finishReason: $data['delta']['stop_reason'] ?? $data['message']['stop_reason'] ?? '',
            usage: $this->usageFormat->fromData($data),
            responseData: $data,
        );
    }

    public function toEventBody(string $data): string|bool {
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

    private function makeReasoningContent(array $data) : string {
        $content = '';
        $nl = '';
        foreach ($data['content'] ?? [] as $part) {
            $content .= $nl . ($part['thinking'] ?? '');
            $nl = PHP_EOL;
        }
        return $content;
    }

    private function normalizeUnknownValues(string $responseBody): string {
        // this is Anthropic specific workaround - the model returns sometimes <UNKNOWN> for missing values
        // when working with tool calls or structured outputs
        return str_replace(':"<UNKNOWN>"', ':null', $responseBody);
    }
}
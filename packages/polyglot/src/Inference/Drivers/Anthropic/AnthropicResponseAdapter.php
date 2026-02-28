<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;

class AnthropicResponseAdapter implements CanTranslateInferenceResponse
{
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    #[\Override]
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
            responseData: $response,
        );
    }

    #[\Override]
    public function fromStreamResponses(iterable $eventBodies, ?HttpResponse $responseData = null): iterable {
        $previous = PartialInferenceResponse::empty();
        foreach ($eventBodies as $eventBody) {
            $delta = $this->fromStreamResponse($eventBody, $responseData);
            if ($delta === null) {
                continue;
            }
            $partial = PartialInferenceResponse::fromDelta($previous, $delta);
            $previous = $partial;
            yield $partial;
        }
    }

    protected function fromStreamResponse(string $eventBody, ?HttpResponse $responseData = null): ?PartialInferenceDelta {
        //$eventBody = $this->normalizeUnknownValues($responseBody);
        $data = json_decode($eventBody, true);
        if (empty($data)) {
            return null;
        }
        return new PartialInferenceDelta(
            contentDelta: $this->makeContentDelta($data),
            reasoningContentDelta: $data['delta']['thinking_delta'] ?? '',
            toolId: $data['content_block']['id'] ?? '',
            toolName: $data['content_block']['name'] ?? '',
            toolArgs: $data['delta']['partial_json'] ?? '',
            finishReason: $data['delta']['stop_reason'] ?? $data['message']['stop_reason'] ?? '',
            usage: $this->usageFormat->fromData($data),
            usageIsCumulative: true,
            responseData: $responseData,
        );
    }

    #[\Override]
    public function toEventBody(string $data): string|bool {
        if (!str_starts_with($data, 'data:')) {
            return '';
        }
        $data = trim(substr($data, 5));
        if ($data === '') {
            return '';
        }
        if ($data === '[DONE]') {
            return false;
        }
        $payload = json_decode($data, true);
        if (is_array($payload) && ($payload['type'] ?? '') === 'message_stop') {
            return false;
        }
        return $data;
    }

    // INTERNAL //////////////////////////////////////////////

    private function makeContent(array $data) : string {
        foreach ($data['content'] ?? [] as $part) {
            if (isset($part['text'])) {
                return $part['text'];
            }
        }
        return '';
    }

    private function makeContentDelta(array $data) : string {
        return $data['delta']['text'] ?? '';
    }

    private function makeToolCalls(array $data) : ToolCalls {
        $toolUseParts = array_filter(
            array: $data['content'] ?? [],
            callback: fn($part) => 'tool_use' === ($part['type'] ?? '')
        );

        return ToolCalls::fromMapper(
            $toolUseParts,
            fn($call) => ToolCall::fromArray([
                'id' => $call['id'] ?? '',
                'name' => $call['name'] ?? '',
                'arguments' => $call['input'] ?? ''
            ])
        );
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

    /**
     * @phpstan-ignore-next-line
     */
    private function normalizeUnknownValues(string $responseBody): string {
        // this is Anthropic specific workaround - the model returns sometimes <UNKNOWN> for missing values
        // when working with tool calls or structured outputs
        return str_replace(':"<UNKNOWN>"', ':null', $responseBody);
    }
}

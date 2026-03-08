<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\ToolCallId;
use Cognesy\Polyglot\Inference\Data\ToolCallIdByStreamIndex;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use JsonException;
use RuntimeException;

class AnthropicResponseAdapter implements CanTranslateInferenceResponse
{
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    #[\Override]
    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        $responseBody = $response->body();
        //$responseBody = $this->normalizeUnknownValues($responseBody);
        $data = $this->decodeResponseData($responseBody);
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
    public function fromStreamDeltas(iterable $eventBodies, ?HttpResponse $responseData = null): iterable {
        $toolIdByIndex = new ToolCallIdByStreamIndex();
        foreach ($eventBodies as $eventBody) {
            $delta = $this->fromStreamResponse($eventBody, $responseData, $toolIdByIndex);
            if ($delta === null) {
                continue;
            }
            yield $delta;
        }
    }

    protected function fromStreamResponse(
        string $eventBody,
        ?HttpResponse $responseData = null,
        ?ToolCallIdByStreamIndex $toolIdByIndex = null,
    ): ?PartialInferenceDelta {
        //$eventBody = $this->normalizeUnknownValues($responseBody);
        $data = $this->decodeJsonData($eventBody, 'Anthropic stream payload');
        if (empty($data)) {
            return null;
        }

        $toolIdByIndex = $toolIdByIndex ?? new ToolCallIdByStreamIndex();
        $blockIndex = $this->extractBlockIndex($data);
        $toolId = $this->resolveToolId($data, $blockIndex, $toolIdByIndex);

        return new PartialInferenceDelta(
            contentDelta: $this->makeContentDelta($data),
            reasoningContentDelta: $data['delta']['thinking_delta'] ?? '',
            toolId: $toolId,
            toolName: (string) ($data['content_block']['name'] ?? ''),
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
        if (str_starts_with($data, 'event:')) {
            return '';
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

    private function extractBlockIndex(array $data): ?string {
        $index = $data['index'] ?? null;
        if (!is_int($index) && !is_float($index) && !is_string($index)) {
            return null;
        }
        return (string) $index;
    }

    private function resolveToolId(
        array $data,
        ?string $blockIndex,
        ToolCallIdByStreamIndex $toolIdByIndex,
    ): string {
        $explicitId = (string) ($data['content_block']['id'] ?? '');
        if ($explicitId !== '') {
            if ($blockIndex !== null) {
                $toolIdByIndex->remember($blockIndex, ToolCallId::fromString($explicitId));
            }
            return $explicitId;
        }

        if ($blockIndex === null) {
            return '';
        }

        $toolCallId = $toolIdByIndex->forIndex($blockIndex);
        return match ($toolCallId) {
            null => '',
            default => $toolCallId->toString(),
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeResponseData(string $payload): array {
        $data = $this->decodeJsonData($payload, 'Anthropic response payload');
        if (!isset($data['content']) || !is_array($data['content'])) {
            throw new RuntimeException('Malformed Anthropic response payload: missing `content` array.');
        }

        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonData(string $payload, string $context): array {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException($context . ' is not valid JSON.', previous: $e);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException($context . ' must decode to an object or array.');
        }

        return $decoded;
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

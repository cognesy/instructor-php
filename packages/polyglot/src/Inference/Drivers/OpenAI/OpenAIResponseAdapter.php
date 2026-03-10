<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Messages\ToolCallId;
use Cognesy\Polyglot\Inference\Data\ToolCallIdByStreamIndex;
use Cognesy\Messages\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCallDelta;
use JsonException;
use RuntimeException;

class OpenAIResponseAdapter implements CanTranslateInferenceResponse
{
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    #[\Override]
    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        $data = $this->decodeResponseData($response->body());
        return new InferenceResponse(
            content: $this->makeContent($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $response,
        );
    }

    #[\Override]
    public function fromStreamDeltas(iterable $eventBodies, ?HttpResponse $responseData = null): iterable {
        $toolIdByIndex = new ToolCallIdByStreamIndex();
        foreach ($eventBodies as $eventBody) {
            $data = $this->decodeJsonData($eventBody, 'OpenAI stream payload');
            if (empty($data)) {
                continue;
            }

            $delta = $this->fromDecodedStreamData($data, $responseData);
            $toolDeltas = $this->extractStreamToolDeltas($data, $toolIdByIndex);

            if ($toolDeltas === []) {
                yield $delta;
                continue;
            }

            $first = $toolDeltas[0];
            yield new PartialInferenceDelta(
                contentDelta: $delta->contentDelta,
                reasoningContentDelta: $delta->reasoningContentDelta,
                toolId: $first->id,
                toolName: $first->name,
                toolArgs: $first->args,
                finishReason: $delta->finishReason,
                usage: $delta->usage,
                usageIsCumulative: $delta->usageIsCumulative,
                responseData: $delta->responseData,
                value: $delta->value,
            );

            foreach (array_slice($toolDeltas, 1) as $tool) {
                yield new PartialInferenceDelta(
                    toolId: $tool->id,
                    toolName: $tool->name,
                    toolArgs: $tool->args,
                );
            }
        }
    }

    protected function fromDecodedStreamData(array $data, ?HttpResponse $responseData = null): PartialInferenceDelta {
        return new PartialInferenceDelta(
            contentDelta: $this->makeContentDelta($data),
            toolId: $this->makeToolId($data),
            toolName: $this->makeToolNameDelta($data),
            toolArgs: $this->makeToolArgsDelta($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
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
        return match(true) {
            $data === '[DONE]' => false,
            default => $data,
        };
    }

    protected function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromArray(array_map(
            callback: fn(array $call) => $this->makeToolCall($call),
            array: $data['choices'][0]['message']['tool_calls'] ?? []
        ));
    }

    protected function makeToolCall(array $data) : ?ToolCall {
        if (empty($data)) {
            return null;
        }
        if (!isset($data['function'])) {
            return null;
        }
        if (!isset($data['id'])) {
            return null;
        }
        return ToolCall::fromArray($data['function'])->withId($data['id']);
    }

    protected function makeContent(array $data): string {
        return $data['choices'][0]['message']['content'] ?? '';
    }

    protected function makeContentDelta(array $data): string {
        return $data['choices'][0]['delta']['content'] ?? '';
    }

    protected function makeToolId(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['id'] ?? '';
    }

    protected function makeToolNameDelta(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '';
    }

    protected function makeToolArgsDelta(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
    }

    /**
     * @return list<ToolCallDelta>
     */
    protected function extractStreamToolDeltas(
        array $data,
        ?ToolCallIdByStreamIndex $toolIdByIndex = null,
    ): array {
        $toolIdByIndex = $toolIdByIndex ?? new ToolCallIdByStreamIndex();
        $calls = $data['choices'][0]['delta']['tool_calls'] ?? [];
        if (!is_array($calls) || $calls === []) {
            return [];
        }

        $toolDeltas = [];
        foreach ($calls as $call) {
            if (!is_array($call)) {
                continue;
            }

            $function = $call['function'] ?? [];
            $toolDeltas[] = new ToolCallDelta(
                id: $this->resolveStreamToolId($call, $toolIdByIndex),
                name: is_array($function) ? (string)($function['name'] ?? '') : '',
                args: is_array($function) ? (string)($function['arguments'] ?? '') : '',
            );
        }

        return $toolDeltas;
    }

    protected function resolveStreamToolId(array $call, ToolCallIdByStreamIndex $toolIdByIndex): string {
        $id = (string)($call['id'] ?? '');
        $index = $call['index'] ?? '';
        $hasIndex = is_int($index) || is_float($index) || is_string($index);
        $indexKey = $hasIndex ? (string)$index : '';

        if ($id !== '') {
            if ($indexKey !== '') {
                $toolIdByIndex->remember($indexKey, ToolCallId::fromString($id));
            }
            return $id;
        }

        if ($indexKey !== '') {
            $remembered = $toolIdByIndex->forIndex($indexKey);
            if ($remembered !== null) {
                return $remembered->toString();
            }
        }

        if ($indexKey !== '') {
            return 'idx:' . $indexKey;
        }

        return '';
    }

    /**
     * @return array<string,mixed>
     */
    protected function decodeResponseData(string $payload): array {
        $data = $this->decodeJsonData($payload, 'OpenAI response payload');
        if (!isset($data['choices']) || !is_array($data['choices'])) {
            throw new RuntimeException('Malformed OpenAI response payload: missing `choices` array.');
        }

        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    protected function decodeJsonData(string $payload, string $context): array {
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
}

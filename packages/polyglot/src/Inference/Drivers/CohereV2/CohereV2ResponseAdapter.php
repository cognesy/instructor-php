<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\CohereV2;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Messages\ToolCallId;
use Cognesy\Polyglot\Inference\Data\ToolCallIdByStreamIndex;
use Cognesy\Messages\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCallDelta;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use RuntimeException;

class CohereV2ResponseAdapter extends OpenAIResponseAdapter
{
    private int $generatedToolCallId = 0;

    #[\Override]
    public function fromStreamDeltas(iterable $eventBodies, ?HttpResponse $responseData = null): iterable {
        $this->generatedToolCallId = 0;

        yield from parent::fromStreamDeltas($eventBodies, $responseData);
    }

    #[\Override]
    public function fromResponse(HttpResponse $response): InferenceResponse {
        $data = $this->decodeJsonData($response->body(), 'Cohere V2 response payload');
        if (!isset($data['message']) || !is_array($data['message'])) {
            throw new RuntimeException('Malformed Cohere V2 response payload: missing `message` object.');
        }

        return new InferenceResponse(
            content: $this->makeContent($data),
            finishReason: $data['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $response,
        );
    }

    #[\Override]
    protected function fromDecodedStreamData(array $data, ?HttpResponse $responseData = null): PartialInferenceDelta {
        return new PartialInferenceDelta(
            contentDelta: $this->makeContentDelta($data),
            toolId: $data['delta']['message']['tool_calls']['function']['id'] ?? '',
            toolName: $data['delta']['message']['tool_calls']['function']['name'] ?? '',
            toolArgs: $data['delta']['message']['tool_calls']['function']['arguments'] ?? '',
            finishReason: $data['delta']['finish_reason'] ?? '',
            usage: $this->usageFormat->fromData($data),
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

    // OVERRIDES - HELPERS ///////////////////////////////////

    #[\Override]
    protected function makeContent(array $data): string {
        return $data['message']['content'][0]['text'] ?? '';
    }

    #[\Override]
    protected function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromArray(array_map(
            callback: fn(array $call) => $this->makeToolCall($call),
            array: $data['message']['tool_calls'] ?? [],
        ));
    }

    #[\Override]
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
        return ToolCall::fromArray($data['function'] ?? [])->withId($data['id'] ?? '');
    }

    #[\Override]
    protected function makeContentDelta(array $data): string {
        $deltaContent = match(true) {
            ([] !== ($data['delta']['message']['content'] ?? [])) => $this->normalizeContent($data['delta']['message']['content']),
            default => '',
        };
        return $deltaContent;
    }

    protected function normalizeContent(array|string $content) : string {
        return is_array($content) ? $content['text'] : $content;
    }

    /**
     * @return list<ToolCallDelta>
     */
    #[\Override]
    protected function extractStreamToolDeltas(
        array $data,
        ?ToolCallIdByStreamIndex $toolIdByIndex = null,
    ): array {
        $toolIdByIndex = $toolIdByIndex ?? new ToolCallIdByStreamIndex();
        $toolCalls = $data['delta']['message']['tool_calls'] ?? [];
        if (!is_array($toolCalls) || $toolCalls === []) {
            return [];
        }

        // Cohere may send one tool call object or an array of tool calls.
        if (array_key_exists('function', $toolCalls)) {
            $toolCalls = [$toolCalls];
        }

        $toolDeltas = [];
        foreach ($toolCalls as $index => $call) {
            if (!is_array($call)) {
                continue;
            }

            $function = $call['function'] ?? [];
            if (!is_array($function)) {
                continue;
            }

            $indexKey = (string)$index;
            $id = (string)($function['id'] ?? $call['id'] ?? '');
            if ($id !== '' && $indexKey !== '') {
                $toolIdByIndex->remember($indexKey, ToolCallId::fromString($id));
            }

            $remembered = $indexKey !== '' ? $toolIdByIndex->forIndex($indexKey) : null;
            $resolvedId = match (true) {
                $id !== '' => $id,
                $remembered !== null => $remembered->toString(),
                default => $this->nextGeneratedToolCallId(),
            };
            $toolDeltas[] = new ToolCallDelta(
                id: $resolvedId,
                name: (string)($function['name'] ?? ''),
                args: (string)($function['arguments'] ?? ''),
            );
        }

        return $toolDeltas;
    }

    private function nextGeneratedToolCallId(): string
    {
        $this->generatedToolCallId += 1;

        return 'cohere-stream:' . $this->generatedToolCallId;
    }
}

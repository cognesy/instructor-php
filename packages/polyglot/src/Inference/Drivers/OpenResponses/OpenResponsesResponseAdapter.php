<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCallId;

/**
 * Translates OpenResponses API responses to InferenceResponse objects.
 *
 * Key differences from Chat Completions:
 * - Response has `output[]` items array instead of `choices[0].message`
 * - Uses `status` instead of `finish_reason`
 * - Content is extracted from message items with type: "message"
 * - Tool calls are in function_call items with type: "function_call"
 * - Reasoning content is in reasoning items with type: "reasoning"
 *
 * Streaming uses semantic events:
 * - `response.output_text.delta` → contentDelta
 * - `response.function_call_arguments.delta` → tool args
 * - `response.completed` → final state
 */
class OpenResponsesResponseAdapter implements CanTranslateInferenceResponse
{
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    #[\Override]
    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        $responseBody = $response->body();
        $data = json_decode($responseBody, true);

        if ($data === null) {
            return null;
        }

        return new InferenceResponse(
            content: $this->extractContent($data),
            finishReason: $this->mapStatusFromData($data),
            toolCalls: $this->extractToolCalls($data),
            reasoningContent: $this->extractReasoningContent($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $response,
        );
    }

    #[\Override]
    public function fromStreamResponses(iterable $eventBodies, ?HttpResponse $responseData = null): iterable {
        $ctx = new OpenResponsesStreamContext();
        $previous = PartialInferenceResponse::empty();

        foreach ($eventBodies as $eventBody) {
            $data = json_decode($eventBody, true);
            if ($data === null || empty($data)) {
                continue;
            }

            $eventType = $data['type'] ?? '';
            $this->updateItemContext($ctx, $data);

            $delta = new PartialInferenceDelta(
                contentDelta: $this->extractStreamContentDelta($ctx, $data, $eventType),
                reasoningContentDelta: $this->extractStreamReasoningDelta($ctx, $data, $eventType),
                toolId: $this->extractStreamToolId($ctx, $data, $eventType),
                toolName: $this->extractStreamToolName($ctx, $data, $eventType),
                toolArgs: $this->extractStreamToolArgs($ctx, $data, $eventType),
                finishReason: $this->extractStreamFinishReason($data, $eventType),
                usage: $this->usageFormat->fromData($data),
                usageIsCumulative: true,
                responseData: $responseData,
            );

            $partial = PartialInferenceResponse::fromDelta($previous, $delta);
            $previous = $partial;
            yield $partial;
        }
    }

    #[\Override]
    public function toEventBody(string $data): string|bool {
        // OpenResponses uses SSE format with "data:" prefix
        if (!str_starts_with($data, 'data:')) {
            // Check for event: lines (OpenResponses sends event type separately)
            if (str_starts_with($data, 'event:')) {
                return ''; // Skip event type lines, we get type from data
            }
            return '';
        }

        $data = trim(substr($data, 5));
        return match(true) {
            $data === '' => '',
            $data === '[DONE]' => false,
            default => $data,
        };
    }

    // RESPONSE EXTRACTION ////////////////////////////////////////////

    /**
     * Extract text content from output items.
     */
    protected function extractContent(array $data): string {
        $output = $data['output'] ?? [];
        $contentParts = [];

        foreach ($output as $item) {
            $type = $item['type'] ?? '';
            if ($type === 'message' && ($item['role'] ?? '') === 'assistant') {
                $content = $item['content'] ?? [];
                foreach ($content as $part) {
                    $partType = $part['type'] ?? '';
                    if ($partType === 'output_text') {
                        $contentParts[] = $part['text'] ?? '';
                    }
                }
            }
        }

        return implode('', $contentParts);
    }

    /**
     * Extract reasoning content from reasoning items.
     */
    protected function extractReasoningContent(array $data): string {
        $output = $data['output'] ?? [];
        $reasoningParts = [];

        foreach ($output as $item) {
            $type = $item['type'] ?? '';
            if ($type === 'reasoning') {
                // Check for content array (raw reasoning)
                $content = $item['content'] ?? [];
                foreach ($content as $part) {
                    $partType = $part['type'] ?? '';
                    if (in_array($partType, ['reasoning_text', 'output_text'], true)) {
                        $reasoningParts[] = $part['text'] ?? '';
                    }
                }
                // Also check summary
                $summary = $item['summary'] ?? [];
                foreach ($summary as $part) {
                    $partType = $part['type'] ?? '';
                    if (in_array($partType, ['summary_text', 'reasoning_summary_text'], true)) {
                        $reasoningParts[] = $part['text'] ?? '';
                    }
                }
            }
        }

        return implode('', $reasoningParts);
    }

    /**
     * Extract tool calls from function_call items.
     */
    protected function extractToolCalls(array $data): ToolCalls {
        $output = $data['output'] ?? [];
        $toolCalls = [];

        foreach ($output as $item) {
            $type = $item['type'] ?? '';
            if ($type === 'function_call') {
                $toolCall = $this->makeToolCall($item);
                if ($toolCall !== null) {
                    $toolCalls[] = $toolCall;
                }
            }
        }

        return ToolCalls::fromArray(array_map(fn($tc) => [
            'id' => (string) ($tc->id() ?? ''),
            'name' => $tc->name(),
            'arguments' => $tc->argsAsJson(),
        ], $toolCalls));
    }

    protected function makeToolCall(array $item): ?ToolCall {
        $callId = $item['call_id'] ?? $item['id'] ?? '';
        $name = $item['name'] ?? '';
        $arguments = $item['arguments'] ?? '{}';

        if (empty($name)) {
            return null;
        }

        return ToolCall::fromArray([
            'name' => $name,
            'arguments' => $arguments,
        ])->withId($callId);
    }

    /**
     * Map OpenResponses status to standard finish reason.
     */
    protected function mapStatusFromData(array $data): string {
        $status = $data['status'] ?? '';
        if ($status !== 'incomplete') {
            return match($status) {
                'completed' => 'stop',
                'failed' => 'error',
                'in_progress' => '',
                default => $status,
            };
        }

        $reason = $data['incomplete_details']['reason'] ?? '';
        return match($reason) {
            'content_filter' => 'content_filter',
            default => 'length',
        };
    }

    // STREAMING EXTRACTION ////////////////////////////////////////////

    protected function updateItemContext(OpenResponsesStreamContext $ctx, array $data): void {
        $eventType = $data['type'] ?? '';

        if ($eventType === 'response.output_item.added' || $eventType === 'response.output_item.done') {
            $this->handleOutputItemEvent($ctx, $data);
        }

        $itemId = $this->resolveItemId($ctx, $data);
        if ($itemId === null) {
            return;
        }
        $ctx->currentItemId = $itemId;
    }

    protected function handleOutputItemEvent(OpenResponsesStreamContext $ctx, array $data): void {
        $item = $data['item'] ?? [];
        if (empty($item)) {
            return;
        }

        $itemId = $item['id'] ?? ($data['item_id'] ?? '');
        if ($itemId === '') {
            return;
        }

        $itemResponseId = OpenResponseItemId::fromString((string) $itemId);
        $ctx->currentItemId = $itemResponseId;
        $ctx->currentItemType = $item['type'] ?? '';

        if ($ctx->currentItemType !== 'function_call') {
            return;
        }

        $callId = $item['call_id'] ?? $itemId;
        $name = $item['name'] ?? '';
        $itemKey = $itemResponseId->toString();
        $ctx->itemToCallId[$itemKey] = ToolCallId::fromString((string) $callId);
        $ctx->itemToName[$itemKey] = (string) $name;
    }

    protected function extractStreamContentDelta(OpenResponsesStreamContext $ctx, array $data, string $eventType): string {
        return match($eventType) {
            'response.output_text.delta' => $this->markOutputTextSeen($ctx, $data, (string) ($data['delta'] ?? '')),
            'response.text.delta' => $this->markOutputTextSeen($ctx, $data, (string) ($data['delta'] ?? '')),
            'response.output_text.done' => $this->maybeEmitDoneText($ctx, $data),
            'response.text.done' => $this->maybeEmitDoneText($ctx, $data),
            default => '',
        };
    }

    protected function extractStreamReasoningDelta(OpenResponsesStreamContext $ctx, array $data, string $eventType): string {
        return match($eventType) {
            'response.reasoning_text.delta' => $this->markReasoningSeen($ctx, $data, (string) ($data['delta'] ?? '')),
            'response.reasoning.delta' => $this->markReasoningSeen($ctx, $data, (string) ($data['delta'] ?? '')),
            'response.reasoning_summary_text.delta' => $this->markReasoningSeen($ctx, $data, (string) ($data['delta'] ?? '')),
            'response.reasoning_text.done' => $this->maybeEmitDoneReasoning($ctx, $data),
            'response.reasoning_summary_text.done' => $this->maybeEmitDoneReasoning($ctx, $data),
            default => '',
        };
    }

    protected function extractStreamToolId(OpenResponsesStreamContext $ctx, array $data, string $eventType): string {
        return match($eventType) {
            'response.output_item.added' => match($data['item']['type'] ?? '') {
                'function_call' => $data['item']['call_id'] ?? $data['item']['id'] ?? '',
                default => '',
            },
            'response.output_item.done' => match($data['item']['type'] ?? '') {
                'function_call' => $data['item']['call_id'] ?? $data['item']['id'] ?? '',
                default => '',
            },
            'response.function_call_arguments.delta' => (string) ($this->resolveCallId($ctx, $data) ?? ''),
            'response.function_call_arguments.done' => (string) ($this->resolveCallId($ctx, $data) ?? ''),
            default => '',
        };
    }

    protected function extractStreamToolName(OpenResponsesStreamContext $ctx, array $data, string $eventType): string {
        return match($eventType) {
            'response.output_item.added' => match($data['item']['type'] ?? '') {
                'function_call' => $data['item']['name'] ?? '',
                default => '',
            },
            'response.output_item.done' => match($data['item']['type'] ?? '') {
                'function_call' => $data['item']['name'] ?? '',
                default => '',
            },
            'response.function_call_arguments.done' => $this->resolveToolName($ctx, $data),
            default => '',
        };
    }

    protected function extractStreamToolArgs(OpenResponsesStreamContext $ctx, array $data, string $eventType): string {
        return match($eventType) {
            'response.function_call_arguments.delta' => $this->markToolArgsSeen($ctx, $data, (string) ($data['delta'] ?? '')),
            'response.function_call_arguments.done' => $this->maybeEmitDoneToolArgs($ctx, $data),
            'response.output_item.done' => match($data['item']['type'] ?? '') {
                'function_call' => $this->maybeEmitDoneToolArgs($ctx, $data, (string) ($data['item']['arguments'] ?? '')),
                default => '',
            },
            default => '',
        };
    }

    protected function extractStreamFinishReason(array $data, string $eventType): string {
        return match($eventType) {
            'response.completed' => $this->mapStatusFromData($data['response'] ?? ['status' => 'completed']),
            'response.failed' => $this->mapStatusFromData($data['response'] ?? ['status' => 'failed']),
            'response.incomplete' => $this->mapStatusFromData($data['response'] ?? ['status' => 'incomplete']),
            'response.done' => $this->mapStatusFromData($data['response'] ?? ['status' => 'completed']),
            default => '',
        };
    }

    protected function resolveItemId(OpenResponsesStreamContext $ctx, array $data): ?OpenResponseItemId {
        if (isset($data['item_id']) && $data['item_id'] !== '') {
            return OpenResponseItemId::fromString((string) $data['item_id']);
        }
        if (isset($data['item']['id']) && $data['item']['id'] !== '') {
            return OpenResponseItemId::fromString((string) $data['item']['id']);
        }
        return $ctx->currentItemId;
    }

    protected function resolveCallId(OpenResponsesStreamContext $ctx, array $data): ?ToolCallId {
        if (isset($data['call_id']) && $data['call_id'] !== '') {
            return ToolCallId::fromString((string) $data['call_id']);
        }
        $itemId = $this->resolveItemId($ctx, $data);
        if ($itemId === null) {
            return null;
        }
        $itemKey = $itemId->toString();
        if (isset($ctx->itemToCallId[$itemKey])) {
            return $ctx->itemToCallId[$itemKey];
        }
        return ToolCallId::fromString($itemKey);
    }

    protected function resolveToolName(OpenResponsesStreamContext $ctx, array $data): string {
        if (isset($data['name']) && $data['name'] !== '') {
            return (string) $data['name'];
        }
        $itemId = $this->resolveItemId($ctx, $data);
        if ($itemId === null) {
            return '';
        }
        return $ctx->itemToName[$itemId->toString()] ?? '';
    }

    protected function markOutputTextSeen(OpenResponsesStreamContext $ctx, array $data, string $delta): string {
        $itemId = $this->resolveItemId($ctx, $data);
        if ($itemId !== null) {
            $ctx->seenOutputTextItems[$itemId->toString()] = true;
        }
        return $delta;
    }

    protected function hasSeenOutputText(OpenResponsesStreamContext $ctx, array $data): bool {
        $itemId = $this->resolveItemId($ctx, $data);
        if ($itemId === null) {
            return false;
        }
        return $ctx->seenOutputTextItems[$itemId->toString()] ?? false;
    }

    protected function maybeEmitDoneText(OpenResponsesStreamContext $ctx, array $data): string {
        if ($this->hasSeenOutputText($ctx, $data)) {
            return '';
        }
        return (string) ($data['text'] ?? '');
    }

    protected function markReasoningSeen(OpenResponsesStreamContext $ctx, array $data, string $delta): string {
        $itemId = $this->resolveItemId($ctx, $data);
        if ($itemId !== null) {
            $ctx->seenReasoningItems[$itemId->toString()] = true;
        }
        return $delta;
    }

    protected function hasSeenReasoning(OpenResponsesStreamContext $ctx, array $data): bool {
        $itemId = $this->resolveItemId($ctx, $data);
        if ($itemId === null) {
            return false;
        }
        return $ctx->seenReasoningItems[$itemId->toString()] ?? false;
    }

    protected function maybeEmitDoneReasoning(OpenResponsesStreamContext $ctx, array $data): string {
        if ($this->hasSeenReasoning($ctx, $data)) {
            return '';
        }
        return (string) ($data['text'] ?? '');
    }

    protected function markToolArgsSeen(OpenResponsesStreamContext $ctx, array $data, string $delta): string {
        $callId = $this->resolveCallId($ctx, $data);
        if ($callId !== null) {
            $callKey = $callId->toString();
            $ctx->toolArgsAccumulated[$callKey] = ($ctx->toolArgsAccumulated[$callKey] ?? '') . $delta;
        }
        return $delta;
    }

    protected function maybeEmitDoneToolArgs(OpenResponsesStreamContext $ctx, array $data, ?string $arguments = null): string {
        $callId = $this->resolveCallId($ctx, $data);
        $fullArgs = (string) ($arguments ?? ($data['arguments'] ?? ''));

        if ($callId === null || $fullArgs === '') {
            return $fullArgs;
        }

        $callKey = $callId->toString();
        $existing = $ctx->toolArgsAccumulated[$callKey] ?? '';
        $ctx->toolArgsAccumulated[$callKey] = $fullArgs;

        if ($existing === '') {
            return $fullArgs;
        }
        if (str_starts_with($fullArgs, $existing)) {
            return substr($fullArgs, strlen($existing));
        }

        return '';
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;

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
    // Track current streaming state for multi-item responses
    private string $currentItemId = '';
    private string $currentItemType = '';
    /** @var array<string, string> */
    private array $itemToCallId = [];
    /** @var array<string, string> */
    private array $itemToName = [];
    /** @var array<string, bool> */
    private array $seenOutputTextItems = [];
    /** @var array<string, bool> */
    private array $seenReasoningItems = [];
    /** @var array<string, string> */
    private array $toolArgsAccumulated = [];

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
    public function fromStreamResponse(string $eventBody, ?HttpResponse $responseData = null): ?PartialInferenceResponse {
        $data = json_decode($eventBody, true);
        if ($data === null || empty($data)) {
            return null;
        }

        $eventType = $data['type'] ?? '';

        // Track item context for multi-item responses
        $this->updateItemContext($data);

        return new PartialInferenceResponse(
            contentDelta: $this->extractStreamContentDelta($data, $eventType),
            reasoningContentDelta: $this->extractStreamReasoningDelta($data, $eventType),
            toolId: $this->extractStreamToolId($data, $eventType),
            toolName: $this->extractStreamToolName($data, $eventType),
            toolArgs: $this->extractStreamToolArgs($data, $eventType),
            finishReason: $this->extractStreamFinishReason($data, $eventType),
            usage: $this->usageFormat->fromData($data),
            usageIsCumulative: true,
            responseData: $responseData,
        );
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
            'id' => $tc->id(),
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

    protected function updateItemContext(array $data): void {
        $eventType = $data['type'] ?? '';

        if ($eventType === 'response.output_item.added' || $eventType === 'response.output_item.done') {
            $this->handleOutputItemEvent($data);
        }

        $itemId = $this->resolveItemId($data);
        if ($itemId === '') {
            return;
        }
        $this->currentItemId = $itemId;
    }

    protected function handleOutputItemEvent(array $data): void {
        $item = $data['item'] ?? [];
        if (empty($item)) {
            return;
        }

        $itemId = $item['id'] ?? ($data['item_id'] ?? '');
        if ($itemId === '') {
            return;
        }

        $this->currentItemId = $itemId;
        $this->currentItemType = $item['type'] ?? '';

        if ($this->currentItemType !== 'function_call') {
            return;
        }

        $callId = $item['call_id'] ?? $itemId;
        $name = $item['name'] ?? '';
        $this->itemToCallId[$itemId] = $callId;
        $this->itemToName[$itemId] = $name;
    }

    protected function extractStreamContentDelta(array $data, string $eventType): string {
        return match($eventType) {
            'response.output_text.delta' => $this->markOutputTextSeen($data, (string) ($data['delta'] ?? '')),
            'response.text.delta' => $this->markOutputTextSeen($data, (string) ($data['delta'] ?? '')),
            'response.output_text.done' => $this->maybeEmitDoneText($data),
            'response.text.done' => $this->maybeEmitDoneText($data),
            default => '',
        };
    }

    protected function extractStreamReasoningDelta(array $data, string $eventType): string {
        return match($eventType) {
            'response.reasoning_text.delta' => $this->markReasoningSeen($data, (string) ($data['delta'] ?? '')),
            'response.reasoning.delta' => $this->markReasoningSeen($data, (string) ($data['delta'] ?? '')),
            'response.reasoning_summary_text.delta' => $this->markReasoningSeen($data, (string) ($data['delta'] ?? '')),
            'response.reasoning_text.done' => $this->maybeEmitDoneReasoning($data),
            'response.reasoning_summary_text.done' => $this->maybeEmitDoneReasoning($data),
            default => '',
        };
    }

    protected function extractStreamToolId(array $data, string $eventType): string {
        return match($eventType) {
            'response.output_item.added' => match($data['item']['type'] ?? '') {
                'function_call' => $data['item']['call_id'] ?? $data['item']['id'] ?? '',
                default => '',
            },
            'response.output_item.done' => match($data['item']['type'] ?? '') {
                'function_call' => $data['item']['call_id'] ?? $data['item']['id'] ?? '',
                default => '',
            },
            'response.function_call_arguments.delta' => $this->resolveCallId($data),
            'response.function_call_arguments.done' => $this->resolveCallId($data),
            default => '',
        };
    }

    protected function extractStreamToolName(array $data, string $eventType): string {
        return match($eventType) {
            'response.output_item.added' => match($data['item']['type'] ?? '') {
                'function_call' => $data['item']['name'] ?? '',
                default => '',
            },
            'response.output_item.done' => match($data['item']['type'] ?? '') {
                'function_call' => $data['item']['name'] ?? '',
                default => '',
            },
            'response.function_call_arguments.done' => $this->resolveToolName($data),
            default => '',
        };
    }

    protected function extractStreamToolArgs(array $data, string $eventType): string {
        return match($eventType) {
            'response.function_call_arguments.delta' => $this->markToolArgsSeen($data, (string) ($data['delta'] ?? '')),
            'response.function_call_arguments.done' => $this->maybeEmitDoneToolArgs($data),
            'response.output_item.done' => match($data['item']['type'] ?? '') {
                'function_call' => $this->maybeEmitDoneToolArgs($data, (string) ($data['item']['arguments'] ?? '')),
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

    protected function resolveItemId(array $data): string {
        if (isset($data['item_id']) && $data['item_id'] !== '') {
            return (string) $data['item_id'];
        }
        if (isset($data['item']['id']) && $data['item']['id'] !== '') {
            return (string) $data['item']['id'];
        }
        return $this->currentItemId;
    }

    protected function resolveCallId(array $data): string {
        if (isset($data['call_id']) && $data['call_id'] !== '') {
            return (string) $data['call_id'];
        }
        $itemId = $this->resolveItemId($data);
        if ($itemId === '') {
            return '';
        }
        if (isset($this->itemToCallId[$itemId]) && $this->itemToCallId[$itemId] !== '') {
            return $this->itemToCallId[$itemId];
        }
        return $itemId;
    }

    protected function resolveToolName(array $data): string {
        if (isset($data['name']) && $data['name'] !== '') {
            return (string) $data['name'];
        }
        $itemId = $this->resolveItemId($data);
        if ($itemId === '') {
            return '';
        }
        return $this->itemToName[$itemId] ?? '';
    }

    protected function markOutputTextSeen(array $data, string $delta): string {
        $itemId = $this->resolveItemId($data);
        if ($itemId !== '') {
            $this->seenOutputTextItems[$itemId] = true;
        }
        return $delta;
    }

    protected function hasSeenOutputText(array $data): bool {
        $itemId = $this->resolveItemId($data);
        if ($itemId === '') {
            return false;
        }
        return $this->seenOutputTextItems[$itemId] ?? false;
    }

    protected function maybeEmitDoneText(array $data): string {
        if ($this->hasSeenOutputText($data)) {
            return '';
        }
        return (string) ($data['text'] ?? '');
    }

    protected function markReasoningSeen(array $data, string $delta): string {
        $itemId = $this->resolveItemId($data);
        if ($itemId !== '') {
            $this->seenReasoningItems[$itemId] = true;
        }
        return $delta;
    }

    protected function hasSeenReasoning(array $data): bool {
        $itemId = $this->resolveItemId($data);
        if ($itemId === '') {
            return false;
        }
        return $this->seenReasoningItems[$itemId] ?? false;
    }

    protected function maybeEmitDoneReasoning(array $data): string {
        if ($this->hasSeenReasoning($data)) {
            return '';
        }
        return (string) ($data['text'] ?? '');
    }

    protected function markToolArgsSeen(array $data, string $delta): string {
        $callId = $this->resolveCallId($data);
        if ($callId !== '') {
            $this->toolArgsAccumulated[$callId] = ($this->toolArgsAccumulated[$callId] ?? '') . $delta;
        }
        return $delta;
    }

    protected function maybeEmitDoneToolArgs(array $data, ?string $arguments = null): string {
        $callId = $this->resolveCallId($data);
        $fullArgs = (string) ($arguments ?? ($data['arguments'] ?? ''));

        if ($callId === '' || $fullArgs === '') {
            return $fullArgs;
        }

        $existing = $this->toolArgsAccumulated[$callId] ?? '';
        $this->toolArgsAccumulated[$callId] = $fullArgs;

        if ($existing === '') {
            return $fullArgs;
        }
        if (str_starts_with($fullArgs, $existing)) {
            return substr($fullArgs, strlen($existing));
        }

        return '';
    }
}

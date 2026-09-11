<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\ContentPart;
use Cognesy\Polyglot\Inference\Assembly\AssistantMessageAssembler;
use Cognesy\Polyglot\Inference\Assembly\AssistantMessageParseResult;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunk;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunks;
use Cognesy\Messages\ToolCall;
use Cognesy\Polyglot\Inference\Drivers\Support\DecodesJsonPayload;
use RuntimeException;

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
    use DecodesJsonPayload;

    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    #[\Override]
    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        $data = $this->decodeResponseData($response->body());

        $parsed = $this->parseAssistantMessage($data);
        return new InferenceResponse(
            finishReason: $this->mapStatusFromData($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $response,
            message: AssistantMessageAssembler::fromParts(
                parts: $parsed->parts(),
                replay: $parsed->replay(),
            )->message(),
        );
    }

    #[\Override]
    public function fromStreamDeltas(iterable $eventBodies, ?HttpResponse $responseData = null): iterable {
        $ctx = new OpenResponsesStreamContext();
        $replay = new OpenResponsesReplayState();

        foreach ($eventBodies as $eventBody) {
            if (trim($eventBody) === '') {
                continue;
            }

            $data = $this->decodeJsonData($eventBody, 'OpenResponses stream payload');
            if (empty($data)) {
                continue;
            }

            $event = $ctx->resolve($data);
            $replay->observe($event);

            yield new PartialInferenceDelta(
                messageChunks: $this->makeStreamMessageChunks($event),
                finishReason: $this->extractStreamFinishReason($event),
                usage: $this->hasUsageData($event->wireData())
                    ? $this->usageFormat->fromData($event->wireData())
                    : null,
                usageIsCumulative: true,
                responseData: $responseData,
                replay: $replay->replay(),
            );
        }
    }

    /**
     * OpenResponses carries usage on the terminal `response.completed` event under
     * `response.usage`, and accepts a top-level `usage` too — OpenResponsesUsageFormat
     * reads both, so the guard must too.
     *
     * Every other event in the stream (`*.delta`, the overwhelming majority) carries
     * neither and used to allocate an all-zero InferenceUsage per delta. A null usage
     * is what StreamingUsageState::apply() already treats as "nothing to add", so this
     * is behaviour-neutral.
     *
     * @param array<string,mixed> $data
     */
    protected function hasUsageData(array $data): bool {
        return !empty($data['usage']) || !empty($data['response']['usage']);
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

    /**
     * @return array<string,mixed>
     */
    private function decodeResponseData(string $payload): array {
        $data = $this->decodeJsonData($payload, 'OpenResponses response payload');
        if (!isset($data['output']) || !is_array($data['output'])) {
            throw new RuntimeException('Malformed OpenResponses response payload: missing `output` array.');
        }

        return $data;
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

    private function parseAssistantMessage(array $data): AssistantMessageParseResult
    {
        $parts = [];
        $replayParts = [];
        foreach ($data['output'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? '');
            if ($type === 'function_call') {
                $toolCall = $this->makeToolCall($item);
                if ($toolCall !== null) {
                    $parts[] = ContentPart::toolCall($toolCall);
                    $replayParts[] = null;
                }
                continue;
            }
            if ($type === 'reasoning') {
                $parts[] = ContentPart::reasoning(OpenResponsesReplay::reasoningText($item));
                $replayParts[] = OpenResponsesReplay::metadataForReasoningItem($item);
                continue;
            }
            if ($type !== 'message' || ($item['role'] ?? '') !== 'assistant') {
                continue;
            }
            foreach ($item['content'] ?? [] as $part) {
                if (!is_array($part) || ($part['type'] ?? '') !== 'output_text') {
                    continue;
                }
                $parts[] = ContentPart::text((string) ($part['text'] ?? ''));
                $replayParts[] = null;
            }
        }
        return AssistantMessageParseResult::fromParts(
            owner: OpenResponsesReplay::OWNER,
            parts: $parts,
            replayParts: $replayParts,
            response: array_filter([
                'id' => $data['id'] ?? null,
                'model' => $data['model'] ?? null,
                'status' => $data['status'] ?? null,
            ], static fn(mixed $value): bool => is_string($value) && $value !== ''),
        );
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

    protected function extractStreamFinishReason(OpenResponsesStreamEvent $event): string {
        return match($event->type()) {
            'response.completed' => $this->mapStatusFromData($event->response() ?? ['status' => 'completed']),
            'response.failed' => $this->mapStatusFromData($event->response() ?? ['status' => 'failed']),
            'response.incomplete' => $this->mapStatusFromData($event->response() ?? ['status' => 'incomplete']),
            'response.done' => $this->mapStatusFromData($event->response() ?? ['status' => 'completed']),
            default => '',
        };
    }

    private function makeStreamMessageChunks(OpenResponsesStreamEvent $event): AssistantMessageChunks {
        $chunks = [];
        if ($event->reasoningDelta() !== '') {
            $chunks[] = AssistantMessageChunk::reasoningDelta(
                $event->reasoningBlockKey(),
                $event->reasoningDelta(),
            );
        }
        if ($event->textDelta() !== '') {
            $chunks[] = AssistantMessageChunk::textDelta(
                $event->textBlockKey(),
                $event->textDelta(),
            );
        }
        if ($event->isToolEvent() && $event->hasToolData()) {
            $chunks[] = AssistantMessageChunk::toolCallDelta(
                index: $event->toolBlockKey(),
                id: (string) ($event->toolCallId() ?? ''),
                name: $event->toolName(),
                arguments: $event->toolArgumentsDelta(),
            );
        }
        return new AssistantMessageChunks(...$chunks);
    }
}

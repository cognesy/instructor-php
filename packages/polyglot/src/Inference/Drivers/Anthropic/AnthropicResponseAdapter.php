<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Enums\ContentType;
use Cognesy\Polyglot\Inference\Assembly\AssistantMessageAssembler;
use Cognesy\Polyglot\Inference\Assembly\AssistantMessageParseResult;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Messages\ToolCallId;
use Cognesy\Polyglot\Inference\Data\ToolCallIdByStreamIndex;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunk;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunks;
use Cognesy\Messages\ToolCall;
use Cognesy\Polyglot\Inference\Drivers\Support\DecodesJsonPayload;
use Cognesy\Utils\Json\Json;
use RuntimeException;

class AnthropicResponseAdapter implements CanTranslateInferenceResponse
{
    use DecodesJsonPayload;

    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    #[\Override]
    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        $responseBody = $response->body();
        //$responseBody = $this->normalizeUnknownValues($responseBody);
        $data = $this->decodeResponseData($responseBody);
        $parsed = $this->parseAssistantMessage($data);
        return new InferenceResponse(
            finishReason: $data['stop_reason'] ?? '',
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
        $toolIdByIndex = new ToolCallIdByStreamIndex();
        $replay = new AnthropicReplayState();
        foreach ($eventBodies as $eventBody) {
            $delta = $this->fromStreamResponse($eventBody, $responseData, $toolIdByIndex, $replay);
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
        ?AnthropicReplayState $replay = null,
    ): ?PartialInferenceDelta {
        //$eventBody = $this->normalizeUnknownValues($responseBody);
        $data = $this->decodeJsonData($eventBody, 'Anthropic stream payload');
        if (empty($data)) {
            return null;
        }

        $toolIdByIndex = $toolIdByIndex ?? new ToolCallIdByStreamIndex();
        $blockIndex = $this->extractBlockIndex($data);
        $toolId = $this->resolveToolId($data, $blockIndex, $toolIdByIndex);
        $replay?->observe($data, $blockIndex);

        return new PartialInferenceDelta(
            messageChunks: $this->makeStreamMessageChunks($data, $blockIndex, $toolId),
            finishReason: $data['delta']['stop_reason'] ?? $data['message']['stop_reason'] ?? '',
            usage: $this->hasUsageData($data) ? $this->usageFormat->fromData($data) : null,
            usageIsCumulative: true,
            responseData: $responseData,
            replay: $replay?->replay(),
        );
    }

    /**
     * Anthropic splits usage across two events: input tokens arrive on
     * `message_start` under `message.usage`, output tokens on `message_delta`
     * under `usage`. Both must pass the guard — a naive `empty($data['usage'])`
     * would drop the input-token count entirely.
     *
     * Everything in between (`content_block_delta`, the overwhelming majority of
     * a stream) carries neither, and used to allocate an all-zero InferenceUsage
     * per delta. A null usage is what StreamingUsageState::apply() already treats
     * as "nothing to add", so this is behaviour-neutral.
     *
     * @param array<string,mixed> $data
     */
    protected function hasUsageData(array $data): bool {
        return !empty($data['usage']) || !empty($data['message']['usage']);
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

    private function parseAssistantMessage(array $data): AssistantMessageParseResult
    {
        $parts = [];
        $replayParts = [];
        foreach ($data['content'] ?? [] as $part) {
            if (!is_array($part)) {
                continue;
            }
            $semantic = $this->makeAssistantPart($part);
            if ($semantic !== null) {
                $parts[] = $semantic;
                $replayParts[] = AnthropicReplay::metadataForWirePart($part);
            }
        }
        return AssistantMessageParseResult::fromParts(
            owner: AnthropicReplay::OWNER,
            parts: $parts,
            replayParts: $replayParts,
            response: array_filter([
                'id' => $data['id'] ?? null,
                'model' => $data['model'] ?? null,
            ], static fn(mixed $value): bool => is_string($value) && $value !== ''),
        );
    }

    /** @param array<string,mixed> $part */
    private function makeAssistantPart(array $part): ?ContentPart
    {
        return match ($part['type'] ?? '') {
            'text' => ContentPart::text((string) ($part['text'] ?? '')),
            'thinking', 'redacted_thinking' => ContentPart::reasoning((string) ($part['thinking'] ?? '')),
            'tool_use' => ContentPart::toolCall(ToolCall::fromArray([
                'id' => $part['id'] ?? '',
                'name' => $part['name'] ?? '',
                'arguments' => match (true) {
                    is_array($part['input'] ?? null) => Json::encode($part['input']),
                    default => $part['input'] ?? '',
                },
            ])),
            default => null,
        };
    }

    /** @param array<string,mixed> $data */
    private function makeStreamMessageChunks(
        array $data,
        ?string $blockIndex,
        string $toolId,
    ): AssistantMessageChunks {
        if ($blockIndex === null) {
            return AssistantMessageChunks::empty();
        }

        $chunks = AssistantMessageChunks::empty();
        $blockType = (string) ($data['content_block']['type'] ?? '');
        $chunks = match ($blockType) {
            'text' => $chunks->add(AssistantMessageChunk::blockStart($blockIndex, ContentType::Text)),
            'thinking', 'redacted_thinking' => $chunks->add(AssistantMessageChunk::blockStart($blockIndex, ContentType::Reasoning)),
            'tool_use' => $chunks
                ->add(AssistantMessageChunk::blockStart($blockIndex, ContentType::ToolCall))
                ->add(AssistantMessageChunk::toolCallDelta(
                    index: $blockIndex,
                    id: $toolId,
                    name: (string) ($data['content_block']['name'] ?? ''),
                )),
            default => $chunks,
        };

        $text = (string) ($data['delta']['text'] ?? '');
        if ($text !== '') {
            $chunks = $chunks->add(AssistantMessageChunk::textDelta($blockIndex, $text));
        }
        $reasoning = (string) ($data['delta']['thinking_delta'] ?? '');
        if ($reasoning !== '') {
            $chunks = $chunks->add(AssistantMessageChunk::reasoningDelta($blockIndex, $reasoning));
        }
        $arguments = (string) ($data['delta']['partial_json'] ?? '');
        if ($arguments !== '') {
            $chunks = $chunks->add(AssistantMessageChunk::toolCallDelta(
                index: $blockIndex,
                id: $toolId,
                arguments: $arguments,
            ));
        }
        return $chunks;
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
        if ($toolCallId !== null) {
            return $toolCallId->toString();
        }

        // No id from the wire and nothing remembered for this block. OpenAI and Gemini
        // both synthesise a stable id from the wire index here; Anthropic used to return
        // '' and leave InferenceStreamState to guess.
        //
        // The synthetic id is deliberately not minted for every event: doing so would
        // attach tool identity to ordinary text blocks in the provider replay envelope.
        if (!$this->isToolBlockEvent($data)) {
            return '';
        }

        $synthetic = 'idx:' . $blockIndex;
        $toolIdByIndex->remember($blockIndex, ToolCallId::fromString($synthetic));
        return $synthetic;
    }

    /**
     * True when the event carries tool-call payload: a `tool_use` block start, a block
     * carrying a tool name, or an arguments fragment.
     *
     * @param array<string,mixed> $data
     */
    private function isToolBlockEvent(array $data): bool {
        return ($data['content_block']['type'] ?? '') === 'tool_use'
            || ($data['content_block']['name'] ?? '') !== ''
            || isset($data['delta']['partial_json']);
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
     * @phpstan-ignore-next-line
     */
    private function normalizeUnknownValues(string $responseBody): string {
        // this is Anthropic specific workaround - the model returns sometimes <UNKNOWN> for missing values
        // when working with tool calls or structured outputs
        return str_replace(':"<UNKNOWN>"', ':null', $responseBody);
    }
}

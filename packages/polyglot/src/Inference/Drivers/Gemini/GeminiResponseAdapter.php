<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\ContentPart;
use Cognesy\Polyglot\Inference\Assembly\AssistantMessageAssembler;
use Cognesy\Polyglot\Inference\Assembly\AssistantMessageParseResult;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Messages\ToolCall;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunk;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunks;
use Cognesy\Utils\Json\Json;
use Cognesy\Polyglot\Inference\Drivers\Support\DecodesJsonPayload;
use RuntimeException;

class GeminiResponseAdapter implements CanTranslateInferenceResponse
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
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
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
        $stream = new GeminiStreamContext();
        foreach ($eventBodies as $eventBody) {
            $data = $this->decodeJsonData($eventBody, 'Gemini stream payload');
            if (empty($data)) {
                continue;
            }

            $delta = $this->fromDecodedStreamData($data, $responseData);
            $chunks = $this->makeStreamMessageChunks($data, $stream);
            yield new PartialInferenceDelta(
                messageChunks: $chunks,
                finishReason: $delta->finishReason,
                usage: $delta->usage,
                usageIsCumulative: $delta->usageIsCumulative,
                responseData: $delta->responseData,
                value: $delta->value,
                replay: $stream->replay(),
            );
        }
    }

    protected function fromDecodedStreamData(array $data, ?HttpResponse $responseData = null): PartialInferenceDelta {
        return new PartialInferenceDelta(
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
            usage: $this->hasUsageData($data) ? $this->usageFormat->fromData($data) : null,
            usageIsCumulative: true,
            responseData: $responseData,
        );
    }

    /**
     * Gemini reports usage under `usageMetadata`, not `usage`.
     *
     * Skipping construction when the block is absent avoids one all-zero
     * InferenceUsage per delta. A null usage is what StreamingUsageState::apply()
     * already treats as "nothing to add", so this is behaviour-neutral.
     *
     * @param array<string,mixed> $data
     */
    protected function hasUsageData(array $data): bool {
        return !empty($data['usageMetadata']);
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

    // INTERNAL /////////////////////////////////////////////

    private function parseAssistantMessage(array $data): AssistantMessageParseResult
    {
        $parts = [];
        $replayParts = [];
        foreach ($this->responseParts($data) as $part) {
            $semantic = $this->makeAssistantPart($part);
            if ($semantic !== null) {
                $parts[] = $semantic;
                $replayParts[] = GeminiReplay::metadataForWirePart($part);
            }
        }
        $candidate = $data['candidates'][0] ?? null;
        $response = match (true) {
            is_array($candidate) => array_filter([
                'id' => $candidate['id'] ?? null,
                'finishReason' => $candidate['finishReason'] ?? null,
            ], static fn(mixed $value): bool => is_string($value) && $value !== ''),
            default => null,
        };
        return AssistantMessageParseResult::fromParts(
            owner: GeminiReplay::OWNER,
            parts: $parts,
            replayParts: $replayParts,
            response: $response,
        );
    }

    /** @param array<string,mixed> $part */
    private function makeAssistantPart(array $part): ?ContentPart
    {
        if (isset($part['functionCall']) && is_array($part['functionCall'])) {
            $call = $part['functionCall'];
            return ContentPart::toolCall(ToolCall::fromArray([
                'id' => $call['id'] ?? '',
                'name' => $call['name'] ?? '',
                'arguments' => match (true) {
                    is_array($call['args'] ?? null) => Json::encode($call['args']),
                    default => $call['args'] ?? '',
                },
            ]));
        }
        if (!isset($part['text']) || !is_string($part['text'])) {
            return null;
        }
        return match ($part['thought'] ?? false) {
            true => ContentPart::reasoning($part['text']),
            default => ContentPart::text($part['text']),
        };
    }

    private function makeStreamMessageChunks(array $data, GeminiStreamContext $stream): AssistantMessageChunks
    {
        $chunks = AssistantMessageChunks::empty();
        $candidateId = (string) ($data['candidates'][0]['id'] ?? 'candidate:0');
        foreach ($this->responseParts($data) as $index => $part) {
            $blockKey = $stream->blockKey($candidateId, $part);
            if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                $call = $part['functionCall'];
                $args = $call['args'] ?? '';
                $chunks = $chunks->add(AssistantMessageChunk::toolCallDelta(
                    index: $blockKey,
                    id: (string) ($call['id'] ?? $this->resolveToolId($candidateId, $index)),
                    name: (string) ($call['name'] ?? ''),
                    arguments: is_array($args) ? Json::encode($args) : '',
                ));
                continue;
            }
            $text = $part['text'] ?? null;
            if (!is_string($text) || $text === '') {
                continue;
            }
            $chunks = match ($part['thought'] ?? false) {
                true => $chunks->add(AssistantMessageChunk::reasoningDelta($blockKey, $text)),
                default => $chunks->add(AssistantMessageChunk::textDelta($blockKey, $text)),
            };
        }
        return $chunks;
    }

    /** @return list<array<string,mixed>> */
    private function responseParts(array $data): array
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        if (!is_array($parts)) {
            return [];
        }
        return array_values(array_filter($parts, is_array(...)));
    }

    private function resolveToolId(string $candidateId, int|string $index): string {
        if ($candidateId !== '') {
            return $candidateId . ':part:' . (string)$index;
        }

        return 'part:' . (string)$index;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeResponseData(string $payload): array {
        $data = $this->decodeJsonData($payload, 'Gemini response payload');
        if (!isset($data['candidates']) || !is_array($data['candidates'])) {
            throw new RuntimeException('Malformed Gemini response payload: missing `candidates` array.');
        }

        return $data;
    }
}

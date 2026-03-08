<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCallDelta;
use Cognesy\Utils\Json\Json;
use JsonException;
use RuntimeException;

class GeminiResponseAdapter implements CanTranslateInferenceResponse
{
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    #[\Override]
    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        $data = $this->decodeResponseData($response->body());
        return new InferenceResponse(
            content: $this->makeContent($data),
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $response,
        );
    }

    #[\Override]
    public function fromStreamDeltas(iterable $eventBodies, ?HttpResponse $responseData = null): iterable {
        foreach ($eventBodies as $eventBody) {
            $data = $this->decodeJsonData($eventBody, 'Gemini stream payload');
            if (empty($data)) {
                continue;
            }

            $delta = $this->fromDecodedStreamData($data, $responseData);
            $toolDeltas = $this->extractStreamToolDeltas($data);

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
            toolId: $data['candidates'][0]['id'] ?? '',
            toolName: $this->makeToolName($data),
            toolArgs: $this->makeToolArgs($data),
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
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

    // INTERNAL /////////////////////////////////////////////

    private function makeToolCalls(array $data) : ToolCalls {
        /** @var array<array<string, mixed>> $parts */
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $functionCalls = array_filter($parts, fn(array $part) => isset($part['functionCall']));
        return ToolCalls::fromMapper(
            array_map(fn(array $part) => $part['functionCall'], $functionCalls),
            fn($call) => ToolCall::fromArray(['name' => $call['name'] ?? '', 'arguments' => $call['args'] ?? '']),
        );
    }

    private function makeContent(array $data) : string {
        $partCount = count($data['candidates'][0]['content']['parts'] ?? []);
        if ($partCount === 1) {
            return $this->makeContentPart($data, 0);
        }
        $content = '';
        $separator = '';
        for ($i = 0; $i < $partCount; $i++) {
            $part = $this->makeContentPart($data, $i);
            if ($part === '') {
                continue;
            }
            $content .= $separator . $part;
            $separator = "\n\n";
        }
        return $content;
    }

    private function makeContentPart(array $data, int $index) : string {
        if (isset($data['candidates'][0]['content']['parts'][$index]['text'])) {
            return $data['candidates'][0]['content']['parts'][$index]['text'];
        }
        return '';
    }

    private function makeContentDelta(array $data): string {
        $partCount = count($data['candidates'][0]['content']['parts'] ?? []);
        if ($partCount === 1) {
            return  $this->makeContentDeltaPart($data, 0);
        }

        $content = '';
        $separator = '';
        for ($i = 0; $i < $partCount; $i++) {
            $part = $this->makeContentDeltaPart($data, $i);
            if ($part === '') {
                continue;
            }
            $content .= $separator . $part;
            $separator = "\n";
        }
        return $content;
    }

    private function makeContentDeltaPart(array $data, int $index) : string {
        if (isset($data['candidates'][0]['content']['parts'][$index]['text'])) {
            return $data['candidates'][0]['content']['parts'][$index]['text'];
        }
        return '';
    }

    private function makeToolName(array $data) : string {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }
            $name = $part['functionCall']['name'] ?? '';
            if ($name !== '') {
                return (string)$name;
            }
        }
        return '';
    }

    private function makeToolArgs(array $data) : string {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }
            $value = $part['functionCall']['args'] ?? '';
            if (is_array($value)) {
                return Json::encode($value);
            }
        }
        return '';
    }

    /**
     * @return list<ToolCallDelta>
     */
    private function extractStreamToolDeltas(array $data): array {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        if (!is_array($parts) || $parts === []) {
            return [];
        }

        $candidateId = (string)($data['candidates'][0]['id'] ?? '');
        $toolDeltas = [];
        foreach ($parts as $index => $part) {
            if (!is_array($part) || !isset($part['functionCall']) || !is_array($part['functionCall'])) {
                continue;
            }
            $functionCall = $part['functionCall'];
            $args = $functionCall['args'] ?? '';
            $explicitId = (string)($functionCall['id'] ?? '');
            $toolDeltas[] = new ToolCallDelta(
                id: $explicitId !== '' ? $explicitId : $this->resolveToolId($candidateId, $index),
                name: (string)($functionCall['name'] ?? ''),
                args: is_array($args) ? Json::encode($args) : '',
            );
        }

        return $toolDeltas;
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
}

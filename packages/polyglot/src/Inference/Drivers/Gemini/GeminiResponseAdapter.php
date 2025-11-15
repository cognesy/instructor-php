<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Json\Json;

class GeminiResponseAdapter implements CanTranslateInferenceResponse
{
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) {}

    #[\Override]
    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        $responseBody = $response->body();
        $data = json_decode($responseBody, true);
        return new InferenceResponse(
            content: $this->makeContent($data),
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $response,
        );
    }

    #[\Override]
    public function fromStreamResponse(string $eventBody, ?HttpResponse $responseData = null): ?PartialInferenceResponse {
        $data = json_decode($eventBody, true);
        if (empty($data)) {
            return null;
        }
        return new PartialInferenceResponse(
            contentDelta: $this->makeContentDelta($data),
            toolId: $data['candidates'][0]['id'] ?? '',
            toolName: $this->makeToolName($data),
            toolArgs: $this->makeToolArgs($data),
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
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

    // INTERNAL /////////////////////////////////////////////

    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromMapper(array_map(
            callback: fn(array $call) => $call['functionCall'] ?? [],
            array: $data['candidates'][0]['content']['parts'] ?? []
        ), fn($call) => ToolCall::fromArray(['name' => $call['name'] ?? '', 'arguments' => $call['args'] ?? '']));
    }

    private function makeContent(array $data) : string {
        $partCount = count($data['candidates'][0]['content']['parts'] ?? []);
        if ($partCount === 1) {
            return $this->makeContentPart($data, 0);
        }
        $content = '';
        for ($i = 0; $i < $partCount; $i++) {
            $part = $this->makeContentPart($data, $i) . "\n\n";
            $content .= $part;
        }
        return $content;
    }

    private function makeContentPart(array $data, int $index) : string {
        if (isset($data['candidates'][0]['content']['parts'][$index]['text'])) {
            return $data['candidates'][0]['content']['parts'][$index]['text'];
        }
        if (isset($data['candidates'][0]['content']['parts'][$index]['functionCall']['args'])) {
            return Json::encode($data['candidates'][0]['content']['parts'][$index]['functionCall']['args']);
        }
        return '';
    }

    private function makeContentDelta(array $data): string {
        $partCount = count($data['candidates'][0]['content']['parts'] ?? []);
        if ($partCount === 1) {
            return  $this->makeContentDeltaPart($data, 0);
        }

        $content = '';
        for ($i = 0; $i < $partCount; $i++) {
            $part = $this->makeContentDeltaPart($data, $i) . "\n";
            $content .= $part;
        }
        return $content;
    }

    private function makeContentDeltaPart(array $data, int $index) : string {
        if (isset($data['candidates'][0]['content']['parts'][$index]['text'])) {
            return $data['candidates'][0]['content']['parts'][$index]['text'];
        }
        if (isset($data['candidates'][0]['content']['parts'][$index]['functionCall']['args'])) {
            return Json::encode($data['candidates'][0]['content']['parts'][$index]['functionCall']['args']);
        }
        return '';
    }

    private function makeToolName(array $data) : string {
        return $data['candidates'][0]['content']['parts'][0]['functionCall']['name'] ?? '';
    }

    private function makeToolArgs(array $data) : string {
        $value = $data['candidates'][0]['content']['parts'][0]['functionCall']['args'] ?? '';
        return is_array($value) ? Json::encode($value) : '';
    }
}

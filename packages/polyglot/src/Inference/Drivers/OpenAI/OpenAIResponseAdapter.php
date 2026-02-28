<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;

class OpenAIResponseAdapter implements CanTranslateInferenceResponse
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
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $response,
        );
    }

    #[\Override]
    public function fromStreamResponses(iterable $eventBodies, ?HttpResponse $responseData = null): iterable {
        $previous = PartialInferenceResponse::empty();
        foreach ($eventBodies as $eventBody) {
            $delta = $this->fromStreamResponse($eventBody, $responseData);
            if ($delta === null) {
                continue;
            }
            $partial = PartialInferenceResponse::fromDelta($previous, $delta);
            $previous = $partial;
            yield $partial;
        }
    }

    protected function fromStreamResponse(string $eventBody, ?HttpResponse $responseData = null): ?PartialInferenceDelta {
        $data = json_decode($eventBody, true);
        if ($data === null || empty($data)) {
            return null;
        }
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
}

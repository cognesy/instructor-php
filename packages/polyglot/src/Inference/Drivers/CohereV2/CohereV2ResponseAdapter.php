<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\CohereV2;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;

class CohereV2ResponseAdapter extends OpenAIResponseAdapter
{
    public function fromResponse(HttpResponse $response): InferenceResponse {
        $responseBody = $response->body();
        $data = json_decode($responseBody, true);
        return new InferenceResponse(
            content: $this->makeContent($data),
            finishReason: $data['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $data,
        );
    }

    public function fromStreamResponse(string $eventBody) : ?PartialInferenceResponse {
        $data = json_decode($eventBody, true);
        if (empty($data)) {
            return null;
        }
        return new PartialInferenceResponse(
            contentDelta: $this->makeContentDelta($data),
            toolId: $data['delta']['message']['tool_calls']['function']['id'] ?? '',
            toolName: $data['delta']['message']['tool_calls']['function']['name'] ?? '',
            toolArgs: $data['delta']['message']['tool_calls']['function']['arguments'] ?? '',
            finishReason: $data['delta']['finish_reason'] ?? '',
            usage: $this->usageFormat->fromData($data),
            responseData: $data,
        );
    }

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

    protected function makeContent(array $data): string {
        $contentMsg = $data['message']['content'][0]['text'] ?? '';
        $contentFnArgs = $data['message']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            !empty($contentMsg) => $contentMsg,
            !empty($contentFnArgs) => $contentFnArgs,
            default => ''
        };
    }

    protected function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromArray(array_map(
            callback: fn(array $call) => $this->makeToolCall($call),
            array: $data['message']['tool_calls'] ?? [],
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
        return ToolCall::fromArray($data['function'] ?? [])?->withId($data['id'] ?? '');
    }

    protected function makeContentDelta(array $data): string {
        $deltaContent = match(true) {
            ([] !== ($data['delta']['message']['content'] ?? [])) => $this->normalizeContent($data['delta']['message']['content']),
            default => '',
        };
        $deltaFnArgs = $data['delta']['message']['tool_calls']['function']['arguments'] ?? '';
        return match(true) {
            '' !== $deltaContent => $deltaContent,
            '' !== $deltaFnArgs => $deltaFnArgs,
            default => ''
        };
    }

    protected function normalizeContent(array|string $content) : string {
        return is_array($content) ? $content['text'] : $content;
    }
}

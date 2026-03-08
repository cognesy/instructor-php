<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Qwen;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;

class QwenResponseAdapter extends OpenAIResponseAdapter
{
    #[\Override]
    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        $data = $this->decodeResponseData($response->body());
        $inferenceResponse = new InferenceResponse(
            content: $this->makeContent($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            reasoningContent: $this->makeReasoningContent($data),
            usage: $this->usageFormat->fromData($data),
            responseData: $response,
        );

        return $inferenceResponse->withReasoningContentFallbackFromContent();
    }

    #[\Override]
    protected function fromDecodedStreamData(array $data, ?HttpResponse $responseData = null): PartialInferenceDelta {
        return new PartialInferenceDelta(
            contentDelta: $this->makeContentDelta($data),
            reasoningContentDelta: $this->makeReasoningContentDelta($data),
            toolId: $this->makeToolId($data),
            toolName: $this->makeToolNameDelta($data),
            toolArgs: $this->makeToolArgsDelta($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            usage: $this->usageFormat->fromData($data),
            usageIsCumulative: true,
            responseData: $responseData,
        );
    }

    private function makeReasoningContent(array $data): string {
        $message = $data['choices'][0]['message'] ?? [];
        if (!is_array($message)) {
            return '';
        }

        return $this->extractReasoning($message);
    }

    private function makeReasoningContentDelta(array $data): string {
        $delta = $data['choices'][0]['delta'] ?? [];
        if (!is_array($delta)) {
            return '';
        }

        return $this->extractReasoning($delta);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function extractReasoning(array $data): string {
        foreach (['reasoning_content', 'reasoning', 'thinking', 'analysis'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (!is_scalar($value)) {
                return '';
            }
            return (string) $value;
        }

        return '';
    }
}

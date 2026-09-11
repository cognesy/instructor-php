<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAICompatible;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Polyglot\Inference\Assembly\AssistantMessageAssembler;
use Cognesy\Polyglot\Inference\Assembly\ThinkTagMessageNormalizer;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;

/**
 * Shared response adapter for OpenAI-compatible providers that emit reasoning
 * content under provider-specific keys (Deepseek, Qwen, GLM, ...):
 * - reasoning extracted from reasoning_content / reasoning / thinking / analysis,
 *   with <think>-tag fallback from content
 * - streamed usage reported cumulatively
 */
class OpenAICompatibleReasoningAdapter extends OpenAIResponseAdapter
{
    #[\Override]
    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        $data = $this->decodeResponseData($response->body());
        $inferenceResponse = new InferenceResponse(
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            usage: $this->usageFormat->fromData($data),
            responseData: $response,
            message: AssistantMessageAssembler::fromParts($this->makeAssistantParts($data))->message(),
        );

        return $inferenceResponse->withMessage(
            ThinkTagMessageNormalizer::normalize($inferenceResponse->message()),
        );
    }

    #[\Override]
    protected function fromDecodedStreamData(array $data, ?HttpResponse $responseData = null): PartialInferenceDelta {
        return new PartialInferenceDelta(
            messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()
                ->withReasoningDelta('openai:reasoning:0', $this->makeReasoningContentDelta($data))
                ->withTextDelta('openai:text:0', $this->makeContentDelta($data)),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            // Inherits OpenAIResponseAdapter::hasUsageData() — these providers use
            // the OpenAI `usage` key. See the note there.
            usage: $this->hasUsageData($data) ? $this->usageFormat->fromData($data) : null,
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

    #[\Override]
    protected function makeAssistantParts(array $data): ContentParts
    {
        $parts = ContentParts::empty();
        $reasoning = $this->makeReasoningContent($data);
        if ($reasoning !== '') {
            $parts = $parts->add(ContentPart::reasoning($reasoning));
        }
        return $parts->append(parent::makeAssistantParts($data));
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

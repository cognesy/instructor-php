<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Deepseek;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;

class DeepseekResponseAdapter extends OpenAIResponseAdapter
{
    #[\Override]
    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        $responseBody = $response->body();
        $data = json_decode($responseBody, true);
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
    public function fromStreamResponse(string $eventBody, ?HttpResponse $responseData = null): ?PartialInferenceResponse {
        $data = json_decode($eventBody, true);
        if ($data === null || empty($data)) {
            return null;
        }
        return new PartialInferenceResponse(
            contentDelta: $this->makeContentDelta($data),
            reasoningContentDelta: $this->makeReasoningContentDelta($data),
            toolId: $this->makeToolId($data),
            toolName: $this->makeToolNameDelta($data),
            toolArgs: $this->makeToolArgsDelta($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            usage: $this->usageFormat->fromData($data),
            responseData: $responseData,
        );
    }

    private function makeReasoningContent(array $data): string {
        $message = $data['choices'][0]['message'] ?? [];
        return match (true) {
            array_key_exists('reasoning_content', $message) => (string) $message['reasoning_content'],
            array_key_exists('reasoning', $message) => (string) $message['reasoning'],
            array_key_exists('analysis', $message) => (string) $message['analysis'],
            default => '',
        };
    }

    private function makeReasoningContentDelta(array $data): string {
        $delta = $data['choices'][0]['delta'] ?? [];
        return match (true) {
            array_key_exists('reasoning_content', $delta) => (string) $delta['reasoning_content'],
            array_key_exists('reasoning', $delta) => (string) $delta['reasoning'],
            array_key_exists('analysis', $delta) => (string) $delta['analysis'],
            default => '',
        };
    }
}

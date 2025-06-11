<?php

namespace Cognesy\Polyglot\Inference\Drivers\Deepseek;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;

class DeepseekResponseAdapter extends OpenAIResponseAdapter
{
    public function fromResponse(array $data): ?InferenceResponse {
        return new InferenceResponse(
            content: $this->makeContent($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            reasoningContent: $data['choices'][0]['message']['reasoning_content'] ?? '',
            usage: $this->usageFormat->fromData($data),
            responseData: $data,
        );
    }

    public function fromStreamResponse(array $data): ?PartialInferenceResponse {
        if ($data === null || empty($data)) {
            return null;
        }
        return new PartialInferenceResponse(
            contentDelta: $this->makeContentDelta($data),
            reasoningContentDelta: $data['choices'][0]['delta']['reasoning_content'] ?? '',
            toolId: $this->makeToolId($data),
            toolName: $this->makeToolNameDelta($data),
            toolArgs: $this->makeToolArgsDelta($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            usage: $this->usageFormat->fromData($data),
            responseData: $data,
        );
    }
}
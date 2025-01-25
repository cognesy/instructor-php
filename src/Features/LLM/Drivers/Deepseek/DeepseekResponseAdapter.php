<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\Deepseek;

use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAI\OpenAIResponseAdapter;

class DeepseekResponseAdapter extends OpenAIResponseAdapter
{
    public function fromResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            reasoningContent: $data['choices'][0]['message']['reasoning_content'] ?? '',
            usage: $this->usageFormat->fromData($data),
            responseData: $data,
        );
    }

    public function fromStreamResponse(array $data): ?PartialLLMResponse {
        if ($data === null || empty($data)) {
            return null;
        }
        return new PartialLLMResponse(
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
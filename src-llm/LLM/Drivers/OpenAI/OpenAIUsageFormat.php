<?php

namespace Cognesy\LLM\LLM\Drivers\OpenAI;

use Cognesy\LLM\LLM\Contracts\CanMapUsage;
use Cognesy\LLM\LLM\Data\Usage;

class OpenAIUsageFormat implements CanMapUsage
{
    public function fromData(array $data): Usage {
        return new Usage(
            inputTokens: $data['usage']['prompt_tokens'] ?? 0,
            outputTokens: $data['usage']['completion_tokens'] ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: $data['usage']['prompt_tokens_details']['cached_tokens'] ?? 0,
            reasoningTokens: $data['usage']['prompt_tokens_details']['reasoning_tokens'] ?? 0,
        );
    }
}
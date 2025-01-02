<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\Groq;

use Cognesy\Instructor\Features\LLM\Contracts\CanMapUsage;
use Cognesy\Instructor\Features\LLM\Data\Usage;

class GroqUsageFormat implements CanMapUsage
{
    public function fromData(array $data): Usage {
        return new Usage(
            inputTokens: $data['x_groq']['usage']['prompt_tokens'] ?? $data['usage']['prompt_tokens'] ?? 0,
            outputTokens: $data['x_groq']['usage']['completion_tokens'] ?? $data['usage']['completion_tokens'] ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}
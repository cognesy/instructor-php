<?php

namespace Cognesy\Polyglot\LLM\Drivers\GeminiOAI;

use Cognesy\Polyglot\LLM\Contracts\CanMapUsage;
use Cognesy\Polyglot\LLM\Data\Usage;

class GeminiOAIUsageFormat implements CanMapUsage
{
    public function fromData(array $data): Usage {
        return new Usage(
            inputTokens: $data['usage']['promptTokens'] ?? 0,
            outputTokens: $data['usage']['completionTokens'] ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}
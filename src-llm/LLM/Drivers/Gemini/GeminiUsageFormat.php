<?php

namespace Cognesy\LLM\LLM\Drivers\Gemini;

use Cognesy\LLM\LLM\Contracts\CanMapUsage;
use Cognesy\LLM\LLM\Data\Usage;

class GeminiUsageFormat implements CanMapUsage
{
    public function fromData(array $data) : Usage {
        return new Usage(
            inputTokens: $data['usageMetadata']['promptTokenCount'] ?? 0,
            outputTokens: $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}
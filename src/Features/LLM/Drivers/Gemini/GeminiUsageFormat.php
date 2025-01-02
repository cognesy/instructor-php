<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\Gemini;

use Cognesy\Instructor\Features\LLM\Contracts\CanMapUsage;
use Cognesy\Instructor\Features\LLM\Data\Usage;

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
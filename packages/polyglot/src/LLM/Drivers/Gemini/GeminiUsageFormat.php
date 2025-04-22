<?php

namespace Cognesy\Polyglot\LLM\Drivers\Gemini;

use Cognesy\Polyglot\LLM\Contracts\CanMapUsage;
use Cognesy\Polyglot\LLM\Data\Usage;

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
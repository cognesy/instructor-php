<?php

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

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
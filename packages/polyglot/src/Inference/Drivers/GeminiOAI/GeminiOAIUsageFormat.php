<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\GeminiOAI;

use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

class GeminiOAIUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data): Usage {
        return new Usage(
            inputTokens: $data['usage']['prompt_tokens'] ?? 0,
            outputTokens: $data['usage']['completion_tokens'] ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}
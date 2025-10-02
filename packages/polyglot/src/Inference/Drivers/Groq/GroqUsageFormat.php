<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Groq;

use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

class GroqUsageFormat implements CanMapUsage
{
    #[\Override]
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
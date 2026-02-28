<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

class OpenAIUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data): Usage {
        $usage = $data['usage'] ?? [];

        return new Usage(
            inputTokens: (int) ($usage['prompt_tokens'] ?? 0),
            outputTokens: (int) ($usage['completion_tokens'] ?? 0),
            cacheWriteTokens: 0,
            cacheReadTokens: (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0),
            reasoningTokens: (int) (
                $usage['completion_tokens_details']['reasoning_tokens']
                ?? $usage['prompt_tokens_details']['reasoning_tokens']
                ?? 0
            ),
        );
    }
}

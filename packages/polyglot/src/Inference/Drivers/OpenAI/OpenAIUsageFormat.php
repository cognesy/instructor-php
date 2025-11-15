<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

class OpenAIUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data): Usage {
        return new Usage(
            inputTokens: (int) ($data['usage']['prompt_tokens'] ?? 0),
            outputTokens: (int) ($data['usage']['completion_tokens'] ?? 0),
            cacheWriteTokens: 0,
            cacheReadTokens: (int) ($data['usage']['prompt_tokens_details']['cached_tokens'] ?? 0),
            reasoningTokens: (int) ($data['usage']['prompt_tokens_details']['reasoning_tokens'] ?? 0),
        );
    }
}

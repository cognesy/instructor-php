<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Gemini;

use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;

class GeminiUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data) : InferenceUsage {
        return new InferenceUsage(
            inputTokens: (int) ($data['usageMetadata']['promptTokenCount'] ?? 0),
            outputTokens: (int) ($data['usageMetadata']['candidatesTokenCount'] ?? 0),
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Gemini;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

class GeminiUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data): Usage {
        return new Usage(
            inputTokens: $data['input_tokens'] ?? 0,
            outputTokens: $data['output_tokens'] ?? 0,
        );
    }
}
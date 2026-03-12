<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Gemini;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;

class GeminiUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data): EmbeddingsUsage {
        return new EmbeddingsUsage(
            inputTokens: (int) ($data['input_tokens'] ?? 0),
        );
    }
}

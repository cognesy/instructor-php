<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\OpenAI;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;

class OpenAIUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data): EmbeddingsUsage
    {
        return EmbeddingsUsage::fromArray([
            'input' => $data['usage']['prompt_tokens'] ?? null,
        ]);
    }
}

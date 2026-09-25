<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Cohere;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;

class CohereUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data): EmbeddingsUsage
    {
        return EmbeddingsUsage::fromArray([
            'input' => $data['meta']['billed_units']['input_tokens'] ?? null,
        ]);
    }
}

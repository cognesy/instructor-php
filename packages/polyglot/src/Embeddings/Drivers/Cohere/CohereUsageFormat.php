<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Cohere;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

class CohereUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data): Usage {
        return new Usage(
            inputTokens: (int) ($data['meta']['billed_units']['input_tokens'] ?? 0),
            outputTokens: (int) ($data['meta']['billed_units']['output_tokens'] ?? 0),
        );
    }
}

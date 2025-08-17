<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Cohere;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

class CohereUsageFormat implements CanMapUsage
{
    public function fromData(array $data): Usage {
        return new Usage(
            inputTokens: $data['meta']['billed_units']['input_tokens'] ?? 0,
            outputTokens: $data['meta']['billed_units']['output_tokens'] ?? 0,
        );
    }
}
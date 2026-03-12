<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\OpenAI;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;

class OpenAIUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data): EmbeddingsUsage {
         $input = (int)($data['usage']['prompt_tokens'] ?? 0);
         return new EmbeddingsUsage(
            inputTokens: $input,
        );
   }
}

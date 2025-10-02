<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\OpenAI;

use Cognesy\Polyglot\Embeddings\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

class OpenAIUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data): Usage {
         $input = (int)($data['usage']['prompt_tokens'] ?? 0);
         $total = (int)($data['usage']['total_tokens'] ?? 0);
         $output = max(0, $total - $input);
         return new Usage(
            inputTokens: $input,
            outputTokens: $output,
        );
   }
}

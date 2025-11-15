<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\CohereV2;

use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

class CohereV2UsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data) : Usage {
        return new Usage(
            inputTokens: (int) ($data['usage']['billed_units']['input_tokens']
                ?? $data['delta']['usage']['billed_units']['input_tokens']
                ?? 0),
            outputTokens: (int) ($data['usage']['billed_units']['output_tokens']
                ?? $data['delta']['usage']['billed_units']['output_tokens']
                ?? 0),
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}

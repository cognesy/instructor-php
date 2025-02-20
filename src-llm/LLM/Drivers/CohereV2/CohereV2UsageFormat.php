<?php

namespace Cognesy\LLM\LLM\Drivers\CohereV2;

use Cognesy\LLM\LLM\Contracts\CanMapUsage;
use Cognesy\LLM\LLM\Data\Usage;

class CohereV2UsageFormat implements CanMapUsage
{
    public function fromData(array $data) : Usage {
        return new Usage(
            inputTokens: $data['usage']['billed_units']['input_tokens']
                ?? $data['delta']['usage']['billed_units']['input_tokens']
                ?? 0,
            outputTokens: $data['usage']['billed_units']['output_tokens']
                ?? $data['delta']['usage']['billed_units']['output_tokens']
                ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}

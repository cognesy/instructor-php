<?php

namespace Cognesy\Polyglot\LLM\Drivers\CohereV2;

use Cognesy\Polyglot\LLM\Contracts\CanMapUsage;
use Cognesy\Polyglot\LLM\Data\Usage;

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

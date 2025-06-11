<?php

namespace Cognesy\Polyglot\Inference\Drivers\CohereV1;

use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

class CohereV1UsageFormat implements CanMapUsage
{
    public function fromData(array $data) : Usage {
        return new Usage(
            inputTokens: $data['meta']['tokens']['input_tokens']
                ?? $data['response']['meta']['tokens']['input_tokens']
                ?? $data['delta']['tokens']['input_tokens']
                ?? 0,
            outputTokens: $data['meta']['tokens']['output_tokens']
                ?? $data['response']['meta']['tokens']['output_tokens']
                ?? $data['delta']['tokens']['input_tokens']
                ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}
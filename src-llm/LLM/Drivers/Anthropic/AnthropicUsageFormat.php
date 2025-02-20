<?php

namespace Cognesy\LLM\LLM\Drivers\Anthropic;

use Cognesy\LLM\LLM\Contracts\CanMapUsage;
use Cognesy\LLM\LLM\Data\Usage;

class AnthropicUsageFormat implements CanMapUsage
{
    public function fromData(array $data) : Usage {
        return new Usage(
            inputTokens: $data['usage']['input_tokens']
                ?? $data['message']['usage']['input_tokens']
                ?? 0,
            outputTokens: $data['usage']['output_tokens']
                ?? $data['message']['usage']['output_tokens']
                ?? 0,
            cacheWriteTokens: $data['usage']['cache_creation_input_tokens']
                ?? $data['message']['usage']['cache_creation_input_tokens']
                ?? 0,
            cacheReadTokens: $data['usage']['cache_read_input_tokens']
                ?? $data['message']['usage']['cache_read_input_tokens']
                ?? 0,
            reasoningTokens: 0,
        );
    }
}
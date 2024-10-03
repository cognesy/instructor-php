<?php

namespace Cognesy\Instructor\Extras\LLM\Data;

class PartialLLMApiResponse
{
    public function __construct(
        public string $delta = '',
        public array  $responseData = [],
        public string $toolName = '',
        public string $toolArgs = '',
        public string $finishReason = '',
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheCreationTokens = 0,
        public int $cacheReadTokens = 0,
    ) {}
}
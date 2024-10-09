<?php

namespace Cognesy\Instructor\Features\LLM\Data;

class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheWriteTokens = 0,
        public int $cacheReadTokens = 0,
        public int $reasoningTokens = 0,
    ) {}

    public function total() : int {
        return $this->inputTokens
            + $this->outputTokens
            + $this->cacheWriteTokens
            + $this->cacheReadTokens
            + $this->reasoningTokens;
    }

    public function accumulate(Usage $usage) : void {
        $this->inputTokens += $usage->inputTokens;
        $this->outputTokens += $usage->outputTokens;
        $this->cacheWriteTokens += $usage->cacheWriteTokens;
        $this->cacheReadTokens += $usage->cacheReadTokens;
        $this->reasoningTokens += $usage->reasoningTokens;
    }
}

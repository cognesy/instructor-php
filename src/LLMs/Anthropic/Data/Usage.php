<?php

namespace Cognesy\Instructor\LLMs\Anthropic\Data;

class Usage
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}

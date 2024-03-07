<?php

namespace Cognesy\Instructor\LLMs;

class LLMResponse
{
    public function __construct(
        /** @var FunctionCall[] */
        public array $toolCalls,
        public string $finishReason,
        public mixed $rawData
    ) {}
}

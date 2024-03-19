<?php

namespace Cognesy\Instructor\LLMs\Data;

class LLMResponse
{
    public function __construct(
        /** @var FunctionCall[] */
        public array   $functionCalls,
        public ?string $finishReason,
        public mixed   $rawData,
        public bool    $isComplete = false,
    ) {}
}

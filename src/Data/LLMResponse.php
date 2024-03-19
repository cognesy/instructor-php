<?php

namespace Cognesy\Instructor\Data;

class LLMResponse
{
    public function __construct(
        /** @var FunctionCall[] */
        public array   $functionCalls,
        public ?string $finishReason,
        public mixed   $rawResponse,
        public bool    $isComplete = false,
    ) {}
}

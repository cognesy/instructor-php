<?php

namespace Cognesy\Instructor\LLMs;

class FunctionCall
{
    public function __construct(
        public string $toolCallId,
        public string $functionName,
        public string $functionArguments,
        public ?string $functionResult = null,
    ) {
    }
}

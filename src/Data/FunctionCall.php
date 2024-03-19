<?php

namespace Cognesy\Instructor\Data;

class FunctionCall
{
    public function __construct(
        public ?string $toolCallId,
        public string  $functionName,
        public string  $functionArgsJson,
    ) {}
}

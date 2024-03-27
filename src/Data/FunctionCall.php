<?php

namespace Cognesy\Instructor\Data;

class FunctionCall
{
    public function __construct(
        public ?string $id,
        public string  $functionName,
        public string  $functionArgsJson,
    ) {}
}

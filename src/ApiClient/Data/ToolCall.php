<?php

namespace Cognesy\Instructor\ApiClient\Data;

class ToolCall
{
    public function __construct(
        public string $name,
        public string $args,
    ) {}
}

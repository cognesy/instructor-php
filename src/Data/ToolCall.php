<?php

namespace Cognesy\Instructor\Data;

class ToolCall
{
    public function __construct(
        public string $name,
        public string $args,
    ) {}
}

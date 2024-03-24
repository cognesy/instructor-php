<?php

namespace Cognesy\Instructor\LLMs\Anthropic\Data;

class TextContent
{
    public string $type = 'text';

    public function __construct(
        public string $text,
    ) {}
}
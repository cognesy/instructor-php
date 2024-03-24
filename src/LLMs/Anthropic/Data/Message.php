<?php
namespace Cognesy\Instructor\LLMs\Anthropic\Data;

class Message
{
    public function __construct(
        public string $role,
        public string|array $content,
    ) {}
}
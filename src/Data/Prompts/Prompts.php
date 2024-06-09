<?php

namespace Cognesy\Instructor\Data\Prompts;

class Prompts
{
    public function __construct(
        private array $prompts = []
    ) {}

    public function get(string $prompt) : Prompt {
        return $this->prompts[$prompt];
    }
}
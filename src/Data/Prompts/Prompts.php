<?php

namespace Cognesy\Instructor\Data\Prompts;

class Prompts
{
    public function __construct(
        private array $prompts = []
    ) {}

    public function get(string $name) : Prompt {
        return $this->prompts[$name];
    }

    public function has(string $name) : bool {
        return isset($this->prompts[$name]);
    }

    public function add(Prompt $prompt) : static {
        $this->prompts[$prompt->name()] = $prompt;
        return $this;
    }
}
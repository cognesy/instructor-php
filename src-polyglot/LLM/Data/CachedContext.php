<?php

namespace Cognesy\Polyglot\LLM\Data;

class CachedContext
{
    public function __construct(
        public string|array $messages = [],
        public array $tools = [],
        public string|array $toolChoice = [],
        public array $responseFormat = [],
    ) {
        if (is_string($messages)) {
            $this->messages = ['role' => 'user', 'content' => $messages];
        }
    }

    public function messages() : array {
        return $this->messages;
    }

    public function tools() : array {
        return $this->tools;
    }

    public function toolChoice() : string|array {
        return $this->toolChoice;
    }

    public function responseFormat() : array {
        return $this->responseFormat;
    }

    public function merged(
        string|array $messages = [],
        array $tools = [],
        string|array $toolChoice = [],
        array $responseFormat = [],
    ) {
        if (is_string($messages) && !empty($messages)) {
            $messages = ['role' => 'user', 'content' => $messages];
        }
        return new CachedContext(
            messages: array_merge($this->messages, $messages),
            tools: empty($tools) ? $this->tools : $tools,
            toolChoice: empty($toolChoice) ? $this->toolChoice : $toolChoice,
            responseFormat: empty($responseFormat) ? $this->responseFormat : $responseFormat,
        );
    }
}

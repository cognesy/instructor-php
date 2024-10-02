<?php

namespace Cognesy\Instructor\Extras\LLM\Data;

use Cognesy\Instructor\Enums\Mode;

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
            array_merge($this->messages, $messages),
            empty($tools) ? $this->tools : $tools,
            empty($toolChoice) ? $this->toolChoice : $toolChoice,
            empty($responseFormat) ? $this->responseFormat : $responseFormat,
        );
    }
}

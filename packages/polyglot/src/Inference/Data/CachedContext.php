<?php

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Utils\Messages\Messages;

class CachedContext
{
    private Messages $messages;
    private array $tools;
    private string|array $toolChoice;
    private array $responseFormat;

    public function __construct(
        string|array $messages = [],
        array $tools = [],
        string|array $toolChoice = [],
        array $responseFormat = [],
    ) {
        $this->messages = Messages::fromAny($messages);
        $this->tools = $tools;
        $this->toolChoice = $toolChoice;
        $this->responseFormat = $responseFormat;
    }

    public function messages() : array {
        return $this->messages->toArray();
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

    public function isEmpty() : bool {
        return $this->messages->isEmpty()
            && empty($this->tools)
            && empty($this->toolChoice)
            && empty($this->responseFormat);
    }

    public function clone() : self {
        return new self(
            messages: $this->messages->toArray(),
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
        );
    }
}

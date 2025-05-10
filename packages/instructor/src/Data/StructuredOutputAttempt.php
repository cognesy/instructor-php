<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\LLM\Data\LLMResponse;

class StructuredOutputAttempt {
    private array $messages;
    private LLMResponse $llmResponse;
    /** @var \Cognesy\Polyglot\LLM\Data\PartialLLMResponse[] */
    private array $partialLLMResponses;
    private array $errors;
    private mixed $output;

    public function __construct(
        array $messages,
        LLMResponse $llmResponse,
        array $partialLLMResponses = [],
        array $errors = [],
        mixed $output = null
    ) {
        $this->messages = $messages;
        $this->llmResponse = $llmResponse;
        $this->partialLLMResponses = $partialLLMResponses;
        $this->errors = $errors;
        $this->output = $output;
    }

    public function isFailed() : bool {
        return count($this->errors) > 0;
    }

    public function messages() : array {
        return $this->messages;
    }

    public function llmResponse() : LLMResponse {
        return $this->llmResponse;
    }

    public function partialLLMResponses() : array {
        return $this->partialLLMResponses;
    }

    public function errors() : array {
        return $this->errors;
    }

    public function output() : mixed {
        return $this->output;
    }
}

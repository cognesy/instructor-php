<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\LLM\Data\InferenceResponse;

class StructuredOutputAttempt {
    private array $messages;
    private InferenceResponse $llmResponse;
    /** @var \Cognesy\Polyglot\LLM\Data\PartialInferenceResponse[] */
    private array $partialLLMResponses;
    private array $errors;
    private mixed $output;

    public function __construct(
        array             $messages,
        InferenceResponse $llmResponse,
        array             $partialLLMResponses = [],
        array             $errors = [],
        mixed             $output = null
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

    public function llmResponse() : InferenceResponse {
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

<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\LLM\Data\InferenceResponse;

class StructuredOutputAttempt {
    private array $messages;
    private InferenceResponse $inferenceResponse;
    /** @var \Cognesy\Polyglot\LLM\Data\PartialInferenceResponse[] */
    private array $partialInferenceResponses;
    private array $errors;
    private mixed $output;

    public function __construct(
        array             $messages,
        InferenceResponse $inferenceResponse,
        array             $partialInferenceResponses = [],
        array             $errors = [],
        mixed             $output = null
    ) {
        $this->messages = $messages;
        $this->inferenceResponse = $inferenceResponse;
        $this->partialInferenceResponses = $partialInferenceResponses;
        $this->errors = $errors;
        $this->output = $output;
    }

    public function isFailed() : bool {
        return count($this->errors) > 0;
    }

    public function messages() : array {
        return $this->messages;
    }

    public function inferenceResponse() : InferenceResponse {
        return $this->inferenceResponse;
    }

    public function partialInferenceResponses() : array {
        return $this->partialInferenceResponses;
    }

    public function errors() : array {
        return $this->errors;
    }

    public function output() : mixed {
        return $this->output;
    }
}

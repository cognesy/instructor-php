<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Extras\LLM\Data\LLMApiResponse;
use Cognesy\Instructor\Extras\LLM\Data\PartialLLMApiResponse;

class Response {
    private array $messages;
    private LLMApiResponse $apiResponse;
    /** @var PartialLLMApiResponse[] */
    private array $partialApiResponses;
    private array $errors;
    private mixed $returnedValue;

    public function __construct(
        array          $messages,
        LLMApiResponse $apiResponse,
        array          $partialApiResponses = [],
        array          $errors = [],
        mixed          $returnedValue = null
    ) {
        $this->messages = $messages;
        $this->apiResponse = $apiResponse;
        $this->partialApiResponses = $partialApiResponses;
        $this->errors = $errors;
        $this->returnedValue = $returnedValue;
    }

    public function isFailed() : bool {
        return count($this->errors) > 0;
    }

    public function messages() : array {
        return $this->messages;
    }

    public function apiResponse() : LLMApiResponse {
        return $this->apiResponse;
    }

    public function partialApiResponses() : array {
        return $this->partialApiResponses;
    }

    public function errors() : array {
        return $this->errors;
    }

    public function returnedValue() : mixed {
        return $this->returnedValue;
    }
}

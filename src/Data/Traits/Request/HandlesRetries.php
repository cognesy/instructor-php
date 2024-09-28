<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Data\Response;
use Cognesy\Instructor\Extras\LLM\Data\ApiResponse;

trait HandlesRetries
{
    private int $maxRetries;
    /** @var Response[] */
    private array $failedResponses = [];
    private Response $response;

    public function maxRetries() : int {
        return $this->maxRetries;
    }

    public function response() : Response {
        return $this->response;
    }

    public function attempts() : array {
        return match(true) {
            !$this->hasAttempts() => [],
            !$this->hasResponse() => $this->failedResponses,
            default => array_merge(
                $this->failedResponses,
                [$this->response]
            )
        };
    }

    public function hasLastResponseFailed() : bool {
        return $this->hasFailures() && !$this->hasResponse();
    }

    public function lastFailedResponse() : ?Response {
        return end($this->failedResponses) ?: null;
    }

    public function hasResponse() : bool {
        return isset($this->response) && $this->response !== null;
    }

    public function hasAttempts() : bool {
        return $this->hasResponse() || $this->hasFailures();
    }

    public function hasFailures() : bool {
        return count($this->failedResponses) > 0;
    }

    public function setResponse(
        array $messages,
        ApiResponse $apiResponse,
        array $partialApiResponses = [],
        mixed $returnedValue = null
    ) {
        $this->response = new Response($messages, $apiResponse, $partialApiResponses, [], $returnedValue);
    }

    public function addFailedResponse(
        array $messages,
        ApiResponse $apiResponse,
        array $partialApiResponses = [],
        array $errors = [],
    ) {
        $this->failedResponses[] = new Response($messages, $apiResponse, $partialApiResponses, $errors, null);
    }
}

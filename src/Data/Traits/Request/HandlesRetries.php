<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Data\Response;

trait HandlesRetries
{
    private string $defaultRetryPrompt = "JSON generated incorrectly, fix following errors: ";
    private string $retryPrompt;

    private int $maxRetries;
    /** @var Response[] */
    private array $failedResponses = [];
    private Response $response;

    public function maxRetries() : int {
        return $this->maxRetries;
    }

    public function retryPrompt() : string {
        return $this->retryPrompt;
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

    public function hasResponse() : bool {
        return $this->response !== null;
    }

    public function hasAttempts() : bool {
        return $this->hasResponse() || $this->hasFailures();
    }

    public function hasFailures() : bool {
        return count($this->failedResponses) > 0;
    }

    public function makeRetryMessages(
        array $messages, string $jsonData, array $errors
    ) : array {
        $messages[] = ['role' => 'assistant', 'content' => $jsonData];
        $messages[] = ['role' => 'user', 'content' => $this->retryPrompt() . implode(", ", $errors)];
        return $messages;
    }

    public function addResponse(
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

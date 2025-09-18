<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data\Traits\StructuredOutputRequest;

use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

trait HandlesRetries
{
    /** @var StructuredOutputAttempt[] */
    private array $failedResponses = [];
    private StructuredOutputAttempt $response;

    public function maxRetries(): int {
        return $this->config->maxRetries();
    }

    public function response(): StructuredOutputAttempt {
        return $this->response;
    }

    public function attempts(): array {
        return match (true) {
            !$this->hasAttempts() => [],
            !$this->hasResponse() => $this->failedResponses,
            default => array_merge(
                $this->failedResponses,
                [$this->response],
            )
        };
    }

    public function hasLastResponseFailed(): bool {
        return $this->hasFailures() && !$this->hasResponse();
    }

    public function lastFailedResponse(): ?StructuredOutputAttempt {
        return end($this->failedResponses) ?: null;
    }

    public function hasResponse(): bool {
        return isset($this->response) && $this->response !== null;
    }

    public function hasAttempts(): bool {
        return $this->hasResponse() || $this->hasFailures();
    }

    public function hasFailures(): bool {
        return count($this->failedResponses) > 0;
    }

    public function setResponse(
        array $messages,
        InferenceResponse $inferenceResponse,
        array $partialInferenceResponses = [],
        mixed $returnedValue = null,
    ) {
        $this->response = new StructuredOutputAttempt($messages, $inferenceResponse, $partialInferenceResponses, [], $returnedValue);
    }

    public function addFailedResponse(
        array $messages,
        InferenceResponse $inferenceResponse,
        array $partialInferenceResponses = [],
        array $errors = [],
    ) {
        $this->failedResponses[] = new StructuredOutputAttempt($messages, $inferenceResponse, $partialInferenceResponses, $errors, null);
    }
}

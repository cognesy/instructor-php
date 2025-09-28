<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;

class StructuredOutputAttempts
{
    private ?StructuredOutputAttempt $response;

    /** @var StructuredOutputAttempt[] */
    private array $failedResponses;

    public function __construct(
        ?StructuredOutputAttempt $response = null,
        array $failedResponses = []
    ) {
        $this->response = $response;
        $this->failedResponses = $failedResponses;
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

    public function toArray(): array {
        return [
            'response' => $this->response ? $this->response->toArray() : null,
            'failed_responses' => array_map(fn($attempt) => $attempt->toArray(), $this->failedResponses),
        ];
    }

    public static function fromArray(array $data): self {
        $response = isset($data['response']) && $data['response'] !== null
            ? StructuredOutputAttempt::fromArray($data['response'])
            : null;
        $failedResponses = isset($data['failed_responses']) && is_array($data['failed_responses'])
            ? array_map(fn($attemptData) => StructuredOutputAttempt::fromArray($attemptData), $data['failed_responses'])
            : [];
        return new self($response, $failedResponses);
    }

    public function clone(): self {
        return new self(
            $this->response ? $this->response->clone() : null,
            array_map(fn($attempt) => $attempt->clone(), $this->failedResponses)
        );
    }
}
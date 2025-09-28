<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

readonly final class StructuredOutputAttempt
{
    private array $messages;
    private InferenceResponse $inferenceResponse;
    /** @var PartialInferenceResponse[] */
    private array $partialInferenceResponses;
    private array $errors;
    private mixed $output;

    public function __construct(
        array $messages,
        InferenceResponse $inferenceResponse,
        array $partialInferenceResponses = [],
        array $errors = [],
        mixed $output = null,
    ) {
        $this->messages = $messages;
        $this->inferenceResponse = $inferenceResponse;
        $this->partialInferenceResponses = $partialInferenceResponses;
        $this->errors = $errors;
        $this->output = $output;
    }

    public function isFailed(): bool {
        return count($this->errors) > 0;
    }

    public function messages(): array {
        return $this->messages;
    }

    public function inferenceResponse(): InferenceResponse {
        return $this->inferenceResponse;
    }

    public function partialInferenceResponses(): array {
        return $this->partialInferenceResponses;
    }

    public function errors(): array {
        return $this->errors;
    }

    public function output(): mixed {
        return $this->output;
    }

    public function toArray(): array {
        return [
            'messages' => $this->messages,
            'inferenceResponse' => $this->inferenceResponse->toArray(),
            'partialInferenceResponses' => array_map(fn(PartialInferenceResponse $r) => $r->toArray(), $this->partialInferenceResponses),
            'errors' => $this->errors,
            'output' => $this->output,
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            $data['messages'] ?? [],
            InferenceResponse::fromArray($data['inferenceResponse'] ?? []),
            array_map(fn(array $r) => PartialInferenceResponse::fromArray($r), $data['partialInferenceResponses'] ?? []),
            $data['errors'] ?? [],
            $data['output'] ?? null,
        );
    }

    public function clone() : self {
        return new self(
            $this->messages,
            $this->inferenceResponse,
            $this->partialInferenceResponses,
            $this->errors,
            $this->output,
        );
    }
}

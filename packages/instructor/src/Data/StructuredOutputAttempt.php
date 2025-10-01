<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

readonly final class StructuredOutputAttempt
{
    public string $id;
    public DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;

    private InferenceExecution $inferenceExecution;
    private bool $isFinalized;
    private array $errors;
    private mixed $output;

    public function __construct(
        ?InferenceExecution $inferenceExecution = null,
        ?bool $isFinalized = false,
        ?array $errors = [],
        mixed $output = null,
        //
        ?string $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->inferenceExecution = $inferenceExecution ?? new InferenceExecution();
        $this->isFinalized = $isFinalized;
        $this->errors = $errors;
        $this->output = $output;
        //
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;
    }

    // MUTATORS //////////////////////////////////////////////////////////

    public function with(
        ?InferenceExecution $inferenceResponse = null,
        ?bool $isFinalized = null,
        ?array $errors = null,
        mixed $output = null,
        //
        ?string $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ): self {
        return new self(
            inferenceExecution: $inferenceResponse ?? $this->inferenceExecution,
            isFinalized: $isFinalized ?? $this->isFinalized,
            errors: $errors ?? $this->errors,
            output: $output ?? $this->output,
            //
            id: $id ?? $this->id,
            createdAt: $createdAt ?? $this->createdAt,
            updatedAt: $updatedAt ?? new DateTimeImmutable(),
        );
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function inferenceResponse(): ?InferenceResponse {
        return $this->inferenceExecution->response();
    }

    public function partialResponses(): PartialInferenceResponseList {
        return $this->inferenceExecution->partialResponses();
    }

    public function inferenceExecution(): InferenceExecution {
        return $this->inferenceExecution;
    }

    public function isFinalized(): bool {
        return $this->isFinalized;
    }

    public function errors(): array {
        return $this->errors;
    }

    public function output(): mixed {
        return $this->output;
    }

    public function hasErrors(): bool {
        return count($this->errors) > 0;
    }

    public function usage(): Usage {
        return $this->inferenceExecution->usage();
    }

    // SERIALIZATION /////////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'inferenceExecution' => $this->inferenceExecution->toArray(),
            'isFinalized' => $this->isFinalized,
            'errors' => $this->errors,
            'output' => $this->output,
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            inferenceExecution: InferenceExecution::fromArray($data['inferenceExecution']),
            isFinalized: $data['isFinalized'] ?? false,
            errors: $data['errors'] ?? [],
            output: $data['output'] ?? null,
            id: $data['id'] ?? null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
            updatedAt: isset($data['updatedAt']) ? new DateTimeImmutable($data['updatedAt']) : null,
        );
    }
}

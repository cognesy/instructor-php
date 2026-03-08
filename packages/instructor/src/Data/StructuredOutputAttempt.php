<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Profiler\TracksObjectCreation;
use DateTimeImmutable;

readonly final class StructuredOutputAttempt
{
    use TracksObjectCreation;

    public StructuredOutputAttemptId $id;
    public DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;

    private ?InferenceResponse $inferenceResponse;
    private ?Usage $usage;
    private bool $isFinalized;
    private array $errors;
    private mixed $output;

    public function __construct(
        ?InferenceResponse $inferenceResponse = null,
        ?Usage $usage = null,
        ?bool $isFinalized = false,
        ?array $errors = [],
        mixed $output = null,
        //
        ?StructuredOutputAttemptId $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->inferenceResponse = $inferenceResponse;
        $this->usage = $usage ?? $inferenceResponse?->usage();
        $this->isFinalized = $isFinalized;
        $this->errors = $errors;
        $this->output = $output;
        //
        $this->id = $id ?? StructuredOutputAttemptId::generate();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;
        $this->trackObjectCreation();
    }

    // MUTATORS //////////////////////////////////////////////////////////

    public function with(
        ?InferenceResponse $inferenceResponse = null,
        ?Usage $usage = null,
        ?bool $isFinalized = null,
        ?array $errors = null,
        mixed $output = null,
        //
        ?StructuredOutputAttemptId $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ): self {
        $resolvedResponse = $inferenceResponse ?? $this->inferenceResponse;

        return $this->copy(
            inferenceResponse: $resolvedResponse,
            usage: $usage ?? $resolvedResponse?->usage(),
            isFinalized: $isFinalized ?? $this->isFinalized,
            errors: $errors ?? $this->errors,
            output: $output ?? $this->output,
            //
            id: $id ?? $this->id,
            createdAt: $createdAt ?? $this->createdAt,
            updatedAt: $updatedAt ?? new DateTimeImmutable(),
        );
    }

    public function withCompletion(
        ?InferenceResponse $inferenceResponse,
        array $errors,
        mixed $output,
    ): self {
        return $this->copy(
            inferenceResponse: $inferenceResponse,
            usage: $inferenceResponse?->usage(),
            isFinalized: true,
            errors: $errors,
            output: $output,
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function inferenceResponse(): ?InferenceResponse {
        return $this->inferenceResponse;
    }

    public function hasInferenceResponse(): bool {
        return $this->inferenceResponse() !== null;
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

    public function isEmpty(): bool {
        return $this->inferenceResponse === null
            && ($this->usage === null || $this->usage->total() === 0)
            && !$this->isFinalized
            && $this->errors === []
            && $this->output === null;
    }

    public function id(): StructuredOutputAttemptId {
        return $this->id;
    }

    public function hasErrors(): bool {
        return count($this->errors) > 0;
    }

    public function usage(): Usage {
        return $this->usage ?? Usage::none();
    }

    // SERIALIZATION /////////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'inferenceResponse' => $this->inferenceResponse?->toArray(),
            'usage' => $this->usage?->toArray(),
            'isFinalized' => $this->isFinalized,
            'errors' => $this->errors,
            'output' => $this->output,
            'id' => $this->id->toString(),
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            inferenceResponse: isset($data['inferenceResponse']) && is_array($data['inferenceResponse'])
                ? InferenceResponse::fromArray($data['inferenceResponse'])
                : null,
            usage: isset($data['usage']) && is_array($data['usage'])
                ? Usage::fromArray($data['usage'])
                : null,
            isFinalized: $data['isFinalized'] ?? false,
            errors: $data['errors'] ?? [],
            output: $data['output'] ?? null,
            id: isset($data['id']) ? new StructuredOutputAttemptId($data['id']) : null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
            updatedAt: isset($data['updatedAt']) ? new DateTimeImmutable($data['updatedAt']) : null,
        );
    }

    private function copy(
        ?InferenceResponse $inferenceResponse,
        ?Usage $usage,
        bool $isFinalized,
        array $errors,
        mixed $output,
        StructuredOutputAttemptId $id,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            inferenceResponse: $inferenceResponse,
            usage: $usage,
            isFinalized: $isFinalized,
            errors: $errors,
            output: $output,
            id: $id,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }
}

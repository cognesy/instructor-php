<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class BatchJob
{
    public function __construct(
        private BatchReference $reference,
        private BatchStatus $status,
        private string $providerStatus,
        private BatchProgress $progress,
        private BatchResultsAvailability $resultsAvailability,
        private DateTimeImmutable $observedAt,
        private ?string $failureCode = null,
        private ?string $failureMessage = null,
    ) {
    }

    public function reference(): BatchReference
    {
        return $this->reference;
    }
    public function status(): BatchStatus
    {
        return $this->status;
    }
    public function providerStatus(): string
    {
        return $this->providerStatus;
    }
    public function progress(): BatchProgress
    {
        return $this->progress;
    }
    public function resultsAvailability(): BatchResultsAvailability
    {
        return $this->resultsAvailability;
    }
    public function observedAt(): DateTimeImmutable
    {
        return $this->observedAt;
    }
    public function failureCode(): ?string
    {
        return $this->failureCode;
    }
    public function failureMessage(): ?string
    {
        return $this->failureMessage;
    }
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference->toArray(),
            'status' => $this->status->value,
            'providerStatus' => $this->providerStatus,
            'progress' => $this->progress->toArray(),
            'resultsAvailability' => $this->resultsAvailability->value,
            'observedAt' => $this->observedAt->format(DATE_ATOM),
            'failureCode' => $this->failureCode,
            'failureMessage' => $this->failureMessage,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (!is_array($data['reference'] ?? null)
            || !is_array($data['progress'] ?? null)
            || !is_string($data['status'] ?? null)
            || !is_string($data['providerStatus'] ?? null)
            || !is_string($data['resultsAvailability'] ?? null)
            || !is_string($data['observedAt'] ?? null)
            || (isset($data['failureCode']) && !is_string($data['failureCode']))
            || (isset($data['failureMessage']) && !is_string($data['failureMessage']))) {
            throw new InvalidArgumentException('Invalid serialized batch job.');
        }

        return new self(
            reference: BatchReference::fromArray($data['reference']),
            status: BatchStatus::from($data['status']),
            providerStatus: $data['providerStatus'],
            progress: BatchProgress::fromArray($data['progress']),
            resultsAvailability: BatchResultsAvailability::from($data['resultsAvailability']),
            observedAt: new DateTimeImmutable($data['observedAt']),
            failureCode: $data['failureCode'] ?? null,
            failureMessage: $data['failureMessage'] ?? null,
        );
    }
}

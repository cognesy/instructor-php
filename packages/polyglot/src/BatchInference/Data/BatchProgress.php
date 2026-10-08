<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use InvalidArgumentException;

final readonly class BatchProgress
{
    public function __construct(
        private ?int $total = null,
        private ?int $processing = null,
        private ?int $completed = null,
        private ?int $failed = null,
        private ?int $cancelled = null,
        private ?int $expired = null,
    ) {
        foreach ([$total, $processing, $completed, $failed, $cancelled, $expired] as $count) {
            if ($count !== null && $count < 0) {
                throw new InvalidArgumentException('Batch progress counts cannot be negative.');
            }
        }
    }

    public function total(): ?int
    {
        return $this->total;
    }
    public function processing(): ?int
    {
        return $this->processing;
    }
    public function completed(): ?int
    {
        return $this->completed;
    }
    public function failed(): ?int
    {
        return $this->failed;
    }
    public function cancelled(): ?int
    {
        return $this->cancelled;
    }
    public function expired(): ?int
    {
        return $this->expired;
    }

    /** @return array<string, ?int> */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'processing' => $this->processing,
            'completed' => $this->completed,
            'failed' => $this->failed,
            'cancelled' => $this->cancelled,
            'expired' => $this->expired,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        foreach (['total', 'processing', 'completed', 'failed', 'cancelled', 'expired'] as $field) {
            if (isset($data[$field]) && !is_int($data[$field])) {
                throw new InvalidArgumentException("Invalid batch progress field: {$field}.");
            }
        }

        return new self(
            total: $data['total'] ?? null,
            processing: $data['processing'] ?? null,
            completed: $data['completed'] ?? null,
            failed: $data['failed'] ?? null,
            cancelled: $data['cancelled'] ?? null,
            expired: $data['expired'] ?? null,
        );
    }
}

<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

final readonly class BatchItemProvenance
{
    /** @param array<string, mixed> $nativeRecord */
    public function __construct(
        private array $nativeRecord,
        private ?string $artifactId = null,
        private ?string $nativeRecordId = null,
        private bool $syntheticDecoderEnvelope = false,
    ) {
    }

    /** @return array<string, mixed> */
    public function nativeRecord(): array
    {
        return $this->nativeRecord;
    }
    public function artifactId(): ?string
    {
        return $this->artifactId;
    }
    public function nativeRecordId(): ?string
    {
        return $this->nativeRecordId;
    }
    public function syntheticDecoderEnvelope(): bool
    {
        return $this->syntheticDecoderEnvelope;
    }
}

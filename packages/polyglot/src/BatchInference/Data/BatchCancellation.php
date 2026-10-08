<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use DateTimeImmutable;

final readonly class BatchCancellation
{
    public function __construct(
        private BatchReference $reference,
        private bool $acknowledged,
        private bool $alreadyTerminal,
        private DateTimeImmutable $observedAt,
        private ?string $providerStatus = null,
    ) {
    }

    public function reference(): BatchReference
    {
        return $this->reference;
    }
    public function acknowledged(): bool
    {
        return $this->acknowledged;
    }
    public function alreadyTerminal(): bool
    {
        return $this->alreadyTerminal;
    }
    public function observedAt(): DateTimeImmutable
    {
        return $this->observedAt;
    }
    public function providerStatus(): ?string
    {
        return $this->providerStatus;
    }
}

<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Observation\Psr;

use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Data\TellEventEnvelope;
use Psr\Log\LoggerInterface;

/** Explicit edge adapter; receives only normalized, redacted Tell envelopes. */
final readonly class PsrTellObserver implements CanObserveTellExecution
{
    public function __construct(private LoggerInterface $logger) {}

    #[\Override]
    public function observe(TellEventEnvelope $event): void {
        $this->logger->info($event->kind, $event->toArray());
    }
}

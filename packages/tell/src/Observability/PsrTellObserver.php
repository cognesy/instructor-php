<?php

declare(strict_types=1);

namespace Cognesy\Tell\Observability;

use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Contracts\Data\TellEventEnvelope;
use Psr\Log\LoggerInterface;

/** Explicit edge adapter; receives only normalized, redacted Tell envelopes. */
final readonly class PsrTellObserver implements CanObserveTellExecution
{
    public function __construct(private LoggerInterface $logger) {}

    public function observe(TellEventEnvelope $event): void
    {
        $this->logger->info($event->kind, $event->toArray());
    }
}

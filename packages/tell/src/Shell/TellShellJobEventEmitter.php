<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

use Cognesy\Tell\Contracts\CanObserveTellShellJobs;
use Cognesy\Tell\Data\TellShellJobEvent;
use DateTimeImmutable;
use Throwable;

/** @internal */
final class TellShellJobEventEmitter
{
    private int $sequence = 0;

    public function __construct(private readonly CanObserveTellShellJobs $observer) {}

    /** @param array<string, int|float|string|bool|null> $metadata */
    public function emit(
        string $kind,
        string $sourceId,
        array $metadata = [],
        ?string $terminal = null,
    ): void {
        try {
            $this->observer->observe(new TellShellJobEvent(
                schema: 'tell.shell-job.event.v1',
                kind: $kind,
                sequence: ++$this->sequence,
                sourceId: $sourceId,
                occurredAt: new DateTimeImmutable(),
                metadata: $metadata,
                terminal: $terminal,
            ));
        } catch (Throwable) {
            // Observation cannot change shell-job ownership or lifecycle.
        }
    }
}

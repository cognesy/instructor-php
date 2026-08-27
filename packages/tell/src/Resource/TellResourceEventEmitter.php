<?php

declare(strict_types=1);

namespace Cognesy\Tell\Resource;

use Cognesy\Tell\Contracts\CanObserveTellResources;
use DateTimeImmutable;

/** @internal */
final class TellResourceEventEmitter
{
    private int $sequence = 0;

    public function __construct(private readonly CanObserveTellResources $observer) {}

    /** @param array<string, int|float|string|bool|null> $metadata */
    public function emit(
        string $kind,
        string $resourceId,
        array $metadata = [],
        ?string $terminal = null,
    ): void {
        try {
            $this->observer->observe(new TellResourceEvent(
                schema: 'tell.resource.event.v1',
                kind: $kind,
                sequence: ++$this->sequence,
                resourceId: $resourceId,
                occurredAt: new DateTimeImmutable,
                metadata: $metadata,
                terminal: $terminal,
            ));
        } catch (\Throwable) {
            // Observation cannot change resource ownership or lifecycle.
        }
    }
}

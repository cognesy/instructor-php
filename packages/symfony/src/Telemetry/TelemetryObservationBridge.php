<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Telemetry;

use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;

final readonly class TelemetryObservationBridge
{
    public function __construct(
        private RuntimeEventBridge $bridge,
    ) {}

    public function __invoke(object $event): void
    {
        $this->bridge->handle($event);
    }
}

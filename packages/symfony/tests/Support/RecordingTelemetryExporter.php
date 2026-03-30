<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Contract\CanFlushTelemetry;
use Cognesy\Telemetry\Domain\Contract\CanShutdownTelemetry;
use Cognesy\Telemetry\Domain\Observation\Observation;

final class RecordingTelemetryExporter implements CanExportObservations, CanFlushTelemetry, CanShutdownTelemetry
{
    /** @var list<Observation> */
    public array $observations = [];

    public int $flushCount = 0;
    public int $shutdownCount = 0;

    #[\Override]
    public function exportObservation(Observation $observation): void
    {
        $this->observations[] = $observation;
    }

    #[\Override]
    public function flush(): void
    {
        $this->flushCount++;
    }

    #[\Override]
    public function shutdown(): void
    {
        $this->shutdownCount++;
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Telemetry;

use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Contract\CanFlushTelemetry;
use Cognesy\Telemetry\Domain\Contract\CanShutdownTelemetry;
use Cognesy\Telemetry\Domain\Observation\Observation;

final class NullTelemetryExporter implements CanExportObservations, CanFlushTelemetry, CanShutdownTelemetry
{
    #[\Override]
    public function exportObservation(Observation $observation): void {}

    #[\Override]
    public function flush(): void {}

    #[\Override]
    public function shutdown(): void {}
}

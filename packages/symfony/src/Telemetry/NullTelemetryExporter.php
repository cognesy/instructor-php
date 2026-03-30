<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Telemetry;

use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Observation\Observation;

final class NullTelemetryExporter implements CanExportObservations
{
    #[\Override]
    public function exportObservation(Observation $observation): void {}
}

<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Testing;

use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Observation\Observation;

final class RecordingTelemetryExporter implements CanExportObservations
{
    /** @var list<Observation> */
    private array $observations = [];

    #[\Override]
    public function exportObservation(Observation $observation): void
    {
        $this->observations[] = $observation;
    }

    /** @return list<Observation> */
    public function observations(): array
    {
        return $this->observations;
    }
}

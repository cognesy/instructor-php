<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Contract;

use Cognesy\Telemetry\Domain\Observation\Observation;

interface CanExportObservations
{
    public function exportObservation(Observation $observation): void;
}

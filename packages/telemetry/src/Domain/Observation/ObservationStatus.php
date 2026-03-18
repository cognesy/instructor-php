<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Observation;

enum ObservationStatus: string
{
    case Ok = 'ok';
    case Error = 'error';
}

<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Observation;

enum ObservationKind: string
{
    case Span = 'span';
    case Log = 'log';
}

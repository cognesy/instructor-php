<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Envelope;

enum OperationKind: string
{
    case RootSpan = 'root_span';
    case Span = 'span';
    case Event = 'event';
    case Metric = 'metric';
}

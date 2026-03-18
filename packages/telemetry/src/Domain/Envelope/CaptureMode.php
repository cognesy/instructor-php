<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Envelope;

enum CaptureMode: string
{
    case None = 'none';
    case Summary = 'summary';
    case Full = 'full';
}

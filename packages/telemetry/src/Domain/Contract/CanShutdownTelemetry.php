<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Contract;

interface CanShutdownTelemetry
{
    public function shutdown(): void;
}

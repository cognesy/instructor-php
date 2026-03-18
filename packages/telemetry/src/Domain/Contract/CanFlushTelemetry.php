<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Contract;

interface CanFlushTelemetry
{
    public function flush(): void;
}

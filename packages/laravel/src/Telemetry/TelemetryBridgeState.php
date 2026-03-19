<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Telemetry;

final class TelemetryBridgeState
{
    private bool $attached = false;

    public function attached(): bool
    {
        return $this->attached;
    }

    public function markAttached(): void
    {
        $this->attached = true;
    }
}

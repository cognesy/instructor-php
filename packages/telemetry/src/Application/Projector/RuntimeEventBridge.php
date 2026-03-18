<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Application\Projector;

use Cognesy\Events\Contracts\CanHandleEvents;

final readonly class RuntimeEventBridge
{
    public function __construct(
        private CanProjectTelemetry $projector,
    ) {}

    public function attachTo(CanHandleEvents $events): void
    {
        $events->wiretap($this->handle(...));
    }

    public function handle(object $event): void
    {
        $this->projector->project($event);
    }
}

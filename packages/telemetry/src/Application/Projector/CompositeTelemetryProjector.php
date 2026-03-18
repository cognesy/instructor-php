<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Application\Projector;

final readonly class CompositeTelemetryProjector implements CanProjectTelemetry
{
    /** @param list<CanProjectTelemetry> $projectors */
    public function __construct(
        private array $projectors,
    ) {}

    #[\Override]
    public function project(object $event): void
    {
        foreach ($this->projectors as $projector) {
            $projector->project($event);
        }
    }
}

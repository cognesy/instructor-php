<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Application\Projector;

interface CanProjectTelemetry
{
    public function project(object $event): void;
}

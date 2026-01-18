<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Contracts;

interface CanTrackExecutionTime
{
    public function withAddedExecutionTime(float $seconds): static;
}

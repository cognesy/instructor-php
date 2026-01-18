<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Contracts;

interface CanMarkStepStarted
{
    public function markStepStarted(): static;
}

<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Contracts;

interface CanMarkExecutionStarted
{
    public function markExecutionStarted(): static;
}

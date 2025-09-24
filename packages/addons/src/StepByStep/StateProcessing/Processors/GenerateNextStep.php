<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing\Processors;

use Cognesy\Addons\StepByStep\Contracts\CanMakeNextStep;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;

class GenerateNextStep implements CanProcessAnyState
{
    public function __construct(
        private CanMakeNextStep $nextStepGenerator,
    ) {}

    public function canProcess(object $state): bool {
        return $state instanceof HasSteps;
    }

    public function process(object $state, ?callable $next = null): object {
        $nextStep = $this->nextStepGenerator->makeNextStep($state);
        $newState = $state
            ->withAddedStep($nextStep)
            ->withCurrentStep($nextStep);
        return $next ? $next($newState) : $newState;
    }
}
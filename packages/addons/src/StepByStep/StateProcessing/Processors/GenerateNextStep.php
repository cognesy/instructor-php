<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing\Processors;

use Cognesy\Addons\StepByStep\Contracts\CanMakeNextStep;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;

/**
 * @implements CanProcessAnyState<HasSteps<object>>
 */
class GenerateNextStep implements CanProcessAnyState
{
    public function __construct(
        private CanMakeNextStep $nextStepGenerator,
    ) {}

    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof HasSteps;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        assert($state instanceof HasSteps);
        $nextStep = $this->nextStepGenerator->makeNextStep($state);
        $newState = $state
            ->withAddedStep($nextStep)
            ->withCurrentStep($nextStep);
        return $next ? $next($newState) : $newState;
    }
}
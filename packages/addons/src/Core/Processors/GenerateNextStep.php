<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Processors;

use Cognesy\Addons\Core\Contracts\CanMakeNextStep;
use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Addons\Core\StateContracts\HasSteps;

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
        $newState = $state->withNewStep($nextStep);
        return $next ? $next($newState) : $newState;
    }
}
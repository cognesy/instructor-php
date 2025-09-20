<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Processors;

use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Addons\Core\StateContracts\HasSteps;
use Cognesy\Addons\Core\StateContracts\HasUsage;
use Cognesy\Addons\Core\StepContracts\HasStepUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

final class AccumulateTokenUsage implements CanProcessAnyState
{
    public function canProcess(object $state): bool {
        return $state instanceof HasUsage
            && $state instanceof HasSteps;
    }

    public function process(object $state, ?callable $next = null): object {
        assert($state instanceof HasSteps);
        $step = $state->currentStep();

        assert($state instanceof HasUsage);
        $usage = Usage::none();
        if ($step !== null && $step instanceof HasStepUsage) {
            $usage = $step->usage();
        }

        $newState = $state->withAccumulatedUsage($usage);

        return $next ? $next($newState) : $newState;
    }
}

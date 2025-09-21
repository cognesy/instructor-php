<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing\Processors;

use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasUsage;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

/**
 * @implements CanProcessAnyState<HasSteps<HasStepUsage>&HasUsage>
 */
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

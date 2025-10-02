<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing\Processors;

use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasUsage;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

/**
 * @implements CanProcessAnyState<HasSteps<object>&HasUsage>
 */
final class AccumulateTokenUsage implements CanProcessAnyState
{
    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof HasUsage
            && $state instanceof HasSteps;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        $newState = $next ? $next($state) : $state;

        assert($newState instanceof HasSteps);
        $step = $newState->currentStep();

        assert($newState instanceof HasUsage);
        $usage = Usage::none();
        if ($step !== null && $step instanceof HasStepUsage) {
            $usage = $step->usage();
        }

        return $newState->withAccumulatedUsage($usage);
    }
}

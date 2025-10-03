<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing;

/**
 * Interface for classes that can apply processing steps to the current state.
 *
 * @template TState of object
 */
interface CanApplyProcessors {
    /**
     * Provide steps to be executed.
     *
     * @param TState $state The current state object.
     * @param (callable(TState): TState)|null $terminalFn Optional terminal function to call after all processors.
     * @return TState The modified state after applying processing steps.
     */
    public function apply(object $state, ?callable $terminalFn = null): object;
}
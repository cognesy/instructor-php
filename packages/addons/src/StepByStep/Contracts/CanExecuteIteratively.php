<?php

namespace Cognesy\Addons\StepByStep\Contracts;

use Generator;

/**
 * @template TState of object
 */
interface CanExecuteIteratively
{
    /**
     * Advance to the next step in the iterative process.
     * @param TState $state
     * @return TState
     */
    public function nextStep(object $state): object;

    /**
     * Determine whether there is a next step to execute.
     * @param TState $state
     */
    public function hasNextStep(object $state): bool;

    /**
     * Perform all steps and return the final state.
     * @param TState $state
     * @return TState
     */
    public function finalStep(object $state): object;

    /**
     * Create an iterator to traverse through each step.
     * @param TState $state
     * @return Generator<mixed, TState, mixed, mixed>
     */
    public function iterator(object $state): iterable;
}
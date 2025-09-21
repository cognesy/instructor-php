<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Contracts;

/**
 * An interface for generating the next step in a process based on the current state.
 *
 * This can be used in various contexts, such as chatbots, workflows, or any system that
 * requires step-by-step progression.
 *
 * @template TState of object
 * @template TStep of object
 */
interface CanMakeNextStep
{
    /**
     * Generate the next step based on the provided state.
     *
     * @param TState $state The current state of the process.
     * @return TStep The resulting step generated from the state.
     */
    public function makeNextStep(object $state): object;
}
<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing;

/**
 * A contract for processing any state object.
 * @template TState of object
 */
interface CanProcessAnyState
{
    /**
     * Determine if this processor can handle the given state.
     *
     * @param object $state The state object to check.
     * @return bool True if the processor can handle the state, false otherwise.
     */
    public function canProcess(object $state): bool;

    /**
     * Process the given state and optionally pass it to the next processor.
     *
     * @param TState $state The state object to process.
     * @param (callable(TState): TState)|null $next The next processor in the chain, or null if processor just returns the processed state.
     * @return TState The processed state object.
     */
    public function process(object $state, ?callable $next = null): object;
}
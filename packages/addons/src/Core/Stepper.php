<?php declare(strict_types=1);

namespace Cognesy\Addons\Core;

use Cognesy\Addons\Core\Continuation\CanDecideToContinue;
use Cognesy\Addons\Core\Contracts\CanApplyProcessors;

/**
 * Minimal step-by-step process executor for use by Chat/ToolUse
 *
 * @template TState of object
 * @template TStep of object
 * @template TProcessor of object
 */
final readonly class Stepper
{
    /**
     * @param CanApplyProcessors<TState> $processors
     * @param CanDecideToContinue<TState> $continuationCriteria
     */
    public function __construct(
        private CanApplyProcessors $processors,
        private CanDecideToContinue $continuationCriteria,
    ) {}

    /**
     * Execute step-by-step process until continuation criteria met.
     * 
     * @param TState $initialState
     * @return TState
     */
    public function run(object $initialState): object {
        $state = $initialState;
        do {
            $state = $this->nextState($state);
        } while ($this->shouldContinue($state));
        return $state;
    }

    /**
     * Execute single step and return new state.
     * 
     * @param TState $state
     * @return TState
     */
    public function nextState(object $state): object {
        return $this->processors->apply($state);
    }

    /**
     * Check if process can continue from current state.
     * 
     * @param TState $state
     * @return bool
     */
    public function shouldContinue(object $state): bool {
        return $this->continuationCriteria->canContinue($state);
    }
}
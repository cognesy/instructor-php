<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing\Processors;

use Closure;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;

/**
 * A processor that wraps a callable for step-level hooks.
 *
 * This provides syntactic sugar for registering simple callbacks as processors.
 * The callback receives the state and can return a modified state.
 *
 * Example usage:
 * ```php
 * // Before step: runs before the next processor
 * $processor = new CallableProcessor(
 *     callback: fn(AgentState $state) => $state->withMetadata('step_started', microtime(true)),
 *     position: 'before',
 * );
 *
 * // After step: runs after the next processor
 * $processor = new CallableProcessor(
 *     callback: function (AgentState $state) {
 *         $duration = microtime(true) - $state->metadata('step_started');
 *         $this->metrics->recordStepDuration($duration);
 *         return $state;
 *     },
 *     position: 'after',
 * );
 * ```
 *
 * @template TState of object
 * @implements CanProcessAnyState<TState>
 */
final class CallableProcessor implements CanProcessAnyState
{
    /** @var Closure(TState): TState */
    private Closure $callback;

    /**
     * @param Closure(TState): TState $callback The callback to execute
     * @param 'before'|'after' $position Whether to run before or after the next processor
     * @param string|null $stateClass Optional: restrict to specific state class
     */
    public function __construct(
        Closure $callback,
        private readonly string $position = 'before',
        private readonly ?string $stateClass = null,
    ) {
        $this->callback = $callback;
    }

    #[\Override]
    public function canProcess(object $state): bool
    {
        if ($this->stateClass === null) {
            return true;
        }

        return $state instanceof $this->stateClass;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object
    {
        if ($this->position === 'before') {
            // Run callback before passing to next
            $state = ($this->callback)($state);
            return $next !== null ? $next($state) : $state;
        }

        // Position is 'after': run callback after next completes
        $newState = $next !== null ? $next($state) : $state;
        return ($this->callback)($newState);
    }
}

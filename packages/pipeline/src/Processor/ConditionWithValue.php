<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

/**
 * Processor that conditionally executes another processor based on processing state.
 */
readonly class ConditionWithValue implements CanProcessState
{
    /**
     * @param Closure(mixed):bool $condition
     * @param CanProcessState $processor
     */
    public function __construct(
        private Closure $condition,
        private CanProcessState $processor,
    ) {}

    public function process(ProcessingState $state): ProcessingState {
        if ($state->isFailure()) {
            return $state; // Don't evaluate condition on failed states
        }
        
        return match(true) {
            ($this->condition)($state->value()) => $this->processor->process($state),
            default => $state,
        };
    }
}
<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

/**
 * Processor that conditionally executes another processor based on processing state.
 */
readonly class ConditionWithState implements CanProcessState
{
    /**
     * @param Closure(ProcessingState):bool $condition
     * @param CanProcessState $processor
     */
    public function __construct(
        private Closure $condition,
        private CanProcessState $processor,
    ) {}

    public function process(ProcessingState $state): ProcessingState {
        return match(true) {
            ($this->condition)($state) => $this->processor->process($state),
            default => $state,
        };
    }
}
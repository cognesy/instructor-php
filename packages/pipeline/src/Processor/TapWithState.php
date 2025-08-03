<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

/**
 * Processor that executes a side-effect processor without modifying the state.
 */
readonly class TapWithState implements CanProcessState
{
    /**
     * callable(ProcessingState):void
     */
    public function __construct(
        private Closure $processor,
    ) {}

    /**
     * @param callable(ProcessingState):void $callback
     */
    public static function fromCallable(callable $callback): self {
        return new self($callback);
    }

    public function process(ProcessingState $state): ProcessingState {
        ($this->processor)($state);
        return $state;
    }
}
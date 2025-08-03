<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

/**
 * Processor that executes a side-effect processor without modifying the state.
 */
readonly class TapWithValue implements CanProcessState
{
    /**
     * callable(mixed):void
     */
    public function __construct(
        private Closure $processor,
    ) {}

    /**
     * @param callable(mixed):void $callback
     */
    public static function fromCallable(callable $callback): self {
        return new self($callback);
    }

    public function process(ProcessingState $state): ProcessingState {
        ($this->processor)($state->value());
        return $state;
    }
}
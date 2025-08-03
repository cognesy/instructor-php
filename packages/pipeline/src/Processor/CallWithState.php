<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\StateFactory;

/**
 * Convenience processor for callables that expect ProcessingState objects.
 * 
 * Use when you need access to the full processing state, tags, or result
 * rather than just the unwrapped value.
 */
readonly class CallWithState implements CanProcessState
{
    /**
     * @param callable(ProcessingState):mixed $callback
     */
    private function __construct(
        private Closure $callback,
        private NullStrategy $onNull = NullStrategy::Allow,
    ) {}

    /**
     * @param callable(ProcessingState):mixed $callback
     */
    public static function fromCallable(callable $callback): self {
        return new self($callback);
    }

    public function process(ProcessingState $state): ProcessingState {
        return StateFactory::executeWithState($this->callback, $state, $this->onNull);
    }
}
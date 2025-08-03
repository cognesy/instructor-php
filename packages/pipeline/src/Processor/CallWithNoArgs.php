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
readonly class CallWithNoArgs implements CanProcessState
{
    /**
     * @param Closure(ProcessingState):void $callable
     */
    private function __construct(
        private Closure $callable,
        private NullStrategy $onNull = NullStrategy::Allow,
    ) {}

    /**
     * @param callable(ProcessingState):void $callable
     */
    public static function from(callable $callable): self {
        return new self($callable);
    }

    public function process(ProcessingState $state): ProcessingState {
        return StateFactory::executeWithState($this->callable, $state, $this->onNull);
    }
}
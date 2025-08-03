<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\StateFactory;

readonly class CallWithValue implements CanProcessState
{

    /**
     * @param Closure(mixed):mixed $callable
     */
    private function __construct(
        private Closure $callable,
        private NullStrategy $onNull = NullStrategy::Allow,
    ) {}

    /**
     * @param callable(mixed):mixed $callable
     */
    public static function fromCallable(callable $callable, ?NullStrategy $onNull = null): self {
        return new self($callable, $onNull ?? NullStrategy::Allow);
    }

    public function process(ProcessingState $state): ProcessingState {
        return StateFactory::executeWithValue($this->callable, $state->value(), $state, $this->onNull);
    }
}
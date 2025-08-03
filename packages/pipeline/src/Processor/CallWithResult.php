<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\StateFactory;
use Cognesy\Utils\Result\Result;

readonly class CallWithResult implements CanProcessState {
    private Closure $callback;

    /**
     * @param callable(Result):mixed $callback
     */
    public function __construct(
        callable $callback,
        private NullStrategy $onNull = NullStrategy::Allow,
    ) {
        $this->callback = $callback;
    }

    /**
     * @param callable(Result):mixed $callback
     */
    public static function fromCallable(callable $callback): self {
        return new self($callback);
    }

    public function process(ProcessingState $state): ProcessingState {
        return StateFactory::executeWithResult($this->callback, $state->result(), $state, $this->onNull);
    }
}
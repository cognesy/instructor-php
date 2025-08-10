<?php

namespace Cognesy\Pipeline\Processor;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\Result\Result;

readonly class Tap implements CanProcessState {
    private function __construct(
        private CanProcessState $processor,
    ) {}

    public static function with(CanProcessState $processor): self {
        return new self($processor);
    }

    /**
     * @param callable():mixed $callable
     */
    public static function withNoArgs(callable $callable): self {
        return new self(Call::withNoArgs($callable));
    }

    /**
     * @param callable(mixed):mixed $callable
     */
    public static function withValue(callable $callable): self {
        return new self(Call::withValue($callable));
    }

    /**
     * @param callable(Result):mixed $callable
     */
    public static function withResult(callable $callable): self {
        return new self(Call::withResult($callable));
    }

    /**
     * @param callable(ProcessingState):mixed $callable
     */
    public static function withState(callable $callable): self {
        return new self(Call::withState($callable));
    }

    public function onNull(NullStrategy $strategy): self {
        return new self(
            $this->processor instanceof Call 
                ? $this->processor->onNull($strategy)
                : $this->processor
        );
    }

    public function process(ProcessingState $state): ProcessingState {
        $this->processor->process($state);
        return $state;
    }
}
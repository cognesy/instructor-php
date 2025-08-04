<?php

namespace Cognesy\Pipeline\Processor;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

readonly class ConditionalCall implements CanProcessState {
    private function __construct(
        private CanProcessState $conditionChecker,
        private CanProcessState $processor,
        private bool $isNegated = false,
    ) {}

    public static function withNoArgs(callable $callable): self {
        return new self(Call::withNoArgs($callable), Call::pass());
    }

    public static function withState(callable $callable): self {
        return new self(Call::withState($callable), Call::pass());
    }

    public static function withValue(callable $callable): self {
        return new self(Call::withValue($callable), Call::pass());
    }

    public static function withResult(callable $callable): self {
        return new self(Call::withResult($callable), Call::pass());
    }

    public function negate(): self {
        return new self(
            $this->conditionChecker,
            $this->processor,
            !$this->isNegated
        );
    }

    public function then(CanProcessState $processor): self {
        return new self(
            $this->conditionChecker,
            $processor,
            $this->isNegated
        );
    }

    public function process(ProcessingState $state): ProcessingState {
        $newState = $this->conditionChecker->process($state);
        return match(true) {
            $newState->isFailure() => $newState,
            $newState->value() && !$this->isNegated => $this->processor->process($state),
            !$newState->value() && $this->isNegated => $this->processor->process($state),
            default => $state,
        };
    }
}
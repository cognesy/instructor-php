<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\Result\Result;

readonly final class ConditionalCall implements CanProcessState {
    private function __construct(
        private CanProcessState $conditionChecker,
        private CanProcessState $operator,
        private bool $isNegated = false,
        private ?CanProcessState $elseOperator = null,
    ) {}

    /** @param callable():ProcessingState $callable */
    public static function withNoArgs(callable $callable): self {
        return new self(Call::withNoArgs($callable), Call::pass());
    }

    /** @param callable(ProcessingState):ProcessingState $callable */
    public static function withState(callable $callable): self {
        return new self(Call::withState($callable), Call::pass());
    }

    /** @param callable(mixed):ProcessingState $callable */
    public static function withValue(callable $callable): self {
        return new self(Call::withValue($callable), Call::pass());
    }

    /** @param callable(Result):ProcessingState $callable */
    public static function withResult(callable $callable): self {
        return new self(Call::withResult($callable), Call::pass());
    }

    public function negate(): self {
        return new self(
            $this->conditionChecker,
            $this->operator,
            !$this->isNegated,
            $this->elseOperator,
        );
    }

    public function then(CanProcessState $operator): self {
        return new self(
            $this->conditionChecker,
            $operator,
            $this->isNegated,
            $this->elseOperator,
        );
    }

    public function otherwise(CanProcessState $else): self {
        return new self(
            $this->conditionChecker,
            $this->operator,
            $this->isNegated,
            $else,
        );
    }

    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        $newState = $this->conditionChecker->process($state, fn($s) => $s);
        if ($newState->isFailure()) {
            return $next ? $next($newState) : $newState;
        }

        $condition = (bool) $newState->value();
        $applyThen = ($condition && !$this->isNegated) || (!$condition && $this->isNegated);

        if ($applyThen) {
            return $this->operator->process($state, $next);
        }

        if (!is_null($this->elseOperator)) {
            return $this->elseOperator->process($state, $next);
        }

        return $next ? $next($state) : $state;
    }
}
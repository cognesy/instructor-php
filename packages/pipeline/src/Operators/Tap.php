<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\StateContracts\CanCarryState;
use Cognesy\Utils\Result\Result;

readonly final class Tap implements CanProcessState {
    private function __construct(
        private CanProcessState $operator,
    ) {}

    public static function with(CanProcessState $operator): self {
        return new self($operator);
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
     * @param callable(CanCarryState):mixed $callable
     */
    public static function withState(callable $callable): self {
        return new self(Call::withState($callable));
    }

    public function onNull(NullStrategy $strategy): self {
        return new self(
            $this->operator instanceof Call
                ? $this->operator->onNull($strategy)
                : $this->operator
        );
    }

    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        $this->operator->process($state, fn($s) => $s);
        return $next ? $next($state) : $state;
    }
}
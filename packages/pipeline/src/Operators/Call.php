<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Pipeline\Tag\SkipProcessingTag;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\Result\Success;
use RuntimeException;
use Throwable;

readonly final class Call implements CanProcessState {
    private Closure $normalizedCall;
    private NullStrategy $onNull;

    /**
     * @param callable(ProcessingState):mixed $callable
     */
    private function __construct(callable $callable, NullStrategy $onNull) {
        $this->onNull = $onNull;
        $this->normalizedCall = $callable(...);
    }

    public static function pass() : CanProcessState {
        return new NoOp();
    }

    /**
     * @param callable(mixed):mixed $callable
     */
    public static function withNoArgs(callable $callable) : self {
        return new self(function (ProcessingState $state) use ($callable) {
            if ($state->isFailure()) {
                return $state;
            }
            return $callable();
        }, NullStrategy::Allow);
    }

    /**
     * @param callable(mixed):mixed $callable
     */
    public static function withValue(callable $callable) : self {
        return new self(function (ProcessingState $state) use ($callable) {
            if ($state->isFailure()) {
                return $state;
            }
            return $callable($state->value());
        }, NullStrategy::Allow);
    }

    /**
     * @param callable(Result):mixed $callable
     */
    public static function withResult(callable $callable) : self {
        return new self(function (ProcessingState $state) use ($callable) {
            return $callable($state->result());
        }, NullStrategy::Allow);
    }

    /**
     * @param callable(ProcessingState):ProcessingState $callable
     */
    public static function withState(callable $callable) : self {
        return new self(function (ProcessingState $state) use ($callable) {
            return $callable($state);
        }, NullStrategy::Allow);
    }

    public function onNull(NullStrategy $strategy): self {
        return new self($this->normalizedCall, $strategy);
    }

    /**
     * @param callable(ProcessingState):ProcessingState $next
     */
    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        try {
            $outputState = ($this->normalizedCall)($state);
        } catch (Throwable $e) {
            $failureState = $state
                ->withResult(Result::failure($e))
                ->withTags(ErrorTag::fromException($e));
            return $next ? $next($failureState) : $failureState;
        }

        if (is_null($outputState)) {
            $nullState = match($this->onNull) {
                NullStrategy::Allow => $state
                    ->withResult(Result::from(null)),
                NullStrategy::Fail => $state
                    ->withResult(Result::failure(new RuntimeException('Null value encountered')))
                    ->withTags(ErrorTag::fromException(new RuntimeException('Null value encountered'))),
                NullStrategy::Skip => $state
                    ->withResult(Result::from(null))
                    ->withTags(new SkipProcessingTag('Null value encountered')),
            };
            return $next ? $next($nullState) : $nullState;
        }

        $modifiedState = match(true) {
            $outputState instanceof ProcessingState => $outputState
                ->mergeInto($state),
            $outputState instanceof Failure => $state
                ->withResult($outputState)
                ->withTags(ErrorTag::fromException($outputState->exception())),
            $outputState instanceof Success => $state
                ->withResult($outputState),
            default => $state
                ->withResult(Result::from($outputState)),
        };

        return $next ? $next($modifiedState) : $modifiedState;
    }
}
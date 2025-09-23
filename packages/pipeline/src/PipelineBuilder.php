<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\Internal\OperatorStack;
use Cognesy\Pipeline\Operators\Call;
use Cognesy\Pipeline\Operators\CallAfter;
use Cognesy\Pipeline\Operators\CallBefore;
use Cognesy\Pipeline\Operators\ConditionalCall;
use Cognesy\Pipeline\Operators\Fail;
use Cognesy\Pipeline\Operators\FailWhen;
use Cognesy\Pipeline\Operators\RawCall;
use Cognesy\Pipeline\Operators\Skip;
use Cognesy\Pipeline\Operators\Tap;
use Cognesy\Pipeline\Operators\TapOnFailure;
use Cognesy\Pipeline\StateContracts\CanCarryState;
use InvalidArgumentException;

class PipelineBuilder
{
    private OperatorStack $steps;
    private OperatorStack $middleware; // per-pipeline execution middleware stack
    private OperatorStack $hooks; // per-processor execution hooks
    private OperatorStack $finalizers;
    private ErrorStrategy $onError;

    public function __construct(
        ErrorStrategy $onError = ErrorStrategy::ContinueWithFailure
    ) {
        $this->steps = new OperatorStack();
        $this->middleware = new OperatorStack();
        $this->hooks = new OperatorStack();
        $this->finalizers = new OperatorStack();
        $this->onError = $onError;
    }

    // MIDDLEWARE SUPPORT /////////////////////////////////////////////////////////////////////

    /**
     * Add middleware to the processing stack.
     *
     * Middleware executes around each processor, allowing for sophisticated
     * cross-cutting concerns like distributed tracing, circuit breakers, etc.
     */
    public function withOperator(CanProcessState ...$operator): static {
        $this->middleware->add(...$operator);
        return $this;
    }

    /**
     * Add middleware at the beginning of the stack (executes first).
     */
    public function prependOperator(CanProcessState ...$operator): static {
        $this->middleware->prepend(...$operator);
        return $this;
    }

    // HOOK API (BACKWARD COMPATIBLE) ////////////////////////////////////////////////////////

    /**
     * Add a hook to execute before each processor.
     *
     * @param callable(CanCarryState):mixed $operation
     */
    public function beforeEach(callable $operation): static {
        $this->hooks->add(CallBefore::with($operation));
        return $this;
    }

    /**
     * Add a hook to execute after each processor.
     *
     * @param callable(CanCarryState):mixed $operation
     */
    public function afterEach(callable $operation): static {
        $this->hooks->add(CallAfter::with($operation));
        return $this;
    }

    /**
     * Add a hook that wraps around each processor execution.
     *
     * The hook will be applied to ALL processors in the pipeline, creating
     * one tag/measurement per processor. Useful for timing, memory tracking,
     * and other measurements that need to capture data before and after
     * each individual processor execution.
     *
     * @param CanProcessState $operation
     */
    public function aroundEach(CanProcessState $operation): static {
        $this->hooks->add($operation);
        return $this;
    }

    /**
     * Add a condition to check if processing should finish early.
     *
     * @param callable(CanCarryState):bool $condition
     */
    public function finishWhen(callable $condition): static {
        $this->hooks->add(Skip::when($condition));
        return $this;
    }

    /**
     * Add a failure handler executed when any step fails.
     *
     * @param callable(CanCarryState):void $operation
     */
    public function onFailure(callable $operation): static {
        $this->hooks->add(TapOnFailure::with($operation));
        return $this;
    }

    /**
     * Fail the pipeline when a condition is met.
     *
     * @param callable(CanCarryState):bool $condition
     */
    public function failWhen(callable $condition, string $message = 'Condition failed'): static {
        $this->hooks->add(FailWhen::with($condition, $message));
        return $this;
    }

    // PROCESSING /////////////////////////////////////////////////////////////////////////////

    /**
     * @param array<callable(mixed):mixed> $operations
     */
    public function throughAll(callable ...$operations): static {
        foreach ($operations as $operation) {
            $this->through($operation);
        }
        return $this;
    }

    /**
     * @param callable(mixed):mixed $operation
     */
    public function through(callable $operation, NullStrategy $onNull = NullStrategy::Fail): static {
        $this->steps->add(Call::withValue($operation)->onNull($onNull));
        return $this;
    }

    public function throughOperator(CanProcessState $operator): static {
        $this->steps->add($operator);
        return $this;
    }

    /**
     * Raw call for fast execution - no normalization, null processing, or error handling.
     *
     * @param callable(CanCarryState, ?callable):CanCarryState $operation
     */
    public function throughRaw(callable $operation): static {
        $this->steps->add(RawCall::with($operation));
        return $this;
    }

    /**
     * @param callable(mixed):bool $condition
     * @param callable(mixed):mixed $then
     * @param callable(mixed):mixed $otherwise
     */
    public function when(
        callable $condition,
        callable $then,
        ?callable $otherwise = null,
    ): static {
        $operator = ConditionalCall::withValue($condition)->then(Call::withValue($then));
        if (!is_null($otherwise)) {
            $operator = $operator->otherwise(Call::withValue($otherwise));
        }
        $this->steps->add($operator);
        return $this;
    }

    /**
     * @param callable(mixed):void $operation
     */
    public function tap(callable $operation): static {
        $this->steps->add(Tap::withValue($operation));
        return $this;
    }

    /**
     * @param callable(CanCarryState):void $operation
     */
    public function tapWithState(callable $operation): static {
        $this->steps->add(Tap::withState($operation));
        return $this;
    }

    /**
     * Add transformation processor
     */
    public function map(callable $operation): static {
        return $this->through($operation);
    }

    public function filter(callable $condition, string $message = 'Value filter condition failed'): static {
        return $this->throughOperator(ConditionalCall::withValue($condition)->negate()->then(Fail::with($message)));
    }

    public function filterWithState(callable $condition, string $message = 'State filter condition failed'): static {
        return $this->throughOperator(ConditionalCall::withState($condition)->negate()->then(Fail::with($message)));
    }

    // EXECUTION //////////////////////////////////////////////////////////////////////////////

    /**
     * @param callable|CanProcessState(CanCarryState):mixed $operation
     */
    public function finally(callable|CanProcessState $operation): static {
        $operation = match (true) {
            $operation instanceof CanProcessState => $operation,
            is_callable($operation) => Call::withState($operation),
            default => throw new InvalidArgumentException('Finalizer must be callable or implement CanFinalizeProcessing'),
        };
        $this->finalizers->add($operation);
        return $this;
    }

    public function create(): Pipeline {
        return new Pipeline(
            steps: $this->steps,
            middleware: $this->middleware,
            hooks: $this->hooks,
            finalizers: $this->finalizers,
            onError: $this->onError,
        );
    }

    public function executeWith(CanCarryState $state): PendingExecution {
        return $this->create()->executeWith($state);
    }
}

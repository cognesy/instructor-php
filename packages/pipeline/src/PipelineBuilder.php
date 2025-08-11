<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Closure;
use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\Internal\OperatorStack;
use Cognesy\Pipeline\Operators\Call;
use Cognesy\Pipeline\Operators\CallAfter;
use Cognesy\Pipeline\Operators\CallBefore;
use Cognesy\Pipeline\Operators\ConditionalCall;
use Cognesy\Pipeline\Operators\Fail;
use Cognesy\Pipeline\Operators\FailWhen;
use Cognesy\Pipeline\Operators\Skip;
use Cognesy\Pipeline\Operators\Tap;
use Cognesy\Pipeline\Operators\TapOnFailure;
use InvalidArgumentException;

class PipelineBuilder
{
    /** @var Closure():mixed $source */
    private Closure $source;
    /** @var array<TagInterface> */
    private array $tags;
    private OperatorStack $operators;
    private OperatorStack $finalizers;
    private OperatorStack $middleware; // per-pipeline execution middleware stack
    private OperatorStack $hooks; // per-processor execution hooks

    /**
     * @param ?callable():mixed $source
     */
    public function __construct(
        ?callable $source = null,
        ?array $tags = null,
    ) {
        $this->source = $source ?? fn() => null;
        $this->tags = $tags ?? [];
        $this->operators = new OperatorStack();
        $this->finalizers = new OperatorStack();
        $this->middleware = new OperatorStack();
        $this->hooks = new OperatorStack();
    }

    /**
     * @param callable():mixed $source
     */
    public function withSource(callable $source): static {
        $this->source = $source;
        return $this;
    }

    public function withInitialValue(mixed $value): static {
        $this->source = fn() => $value;
        return $this;
    }

    public function withTags(TagInterface ...$tags): static {
        $this->tags = array_merge($this->tags, $tags);
        return $this;
    }

    // MIDDLEWARE SUPPORT /////////////////////////////////////////////////////////////////////

    /**
     * Add middleware to the processing stack.
     *
     * Middleware executes around each processor, allowing for sophisticated
     * cross-cutting concerns like distributed tracing, circuit breakers, etc.
     */
    public function withMiddleware(CanControlStateProcessing ...$middleware): static {
        $this->middleware->add(...$middleware);
        return $this;
    }

    /**
     * Add middleware at the beginning of the stack (executes first).
     */
    public function prependMiddleware(CanControlStateProcessing ...$middleware): static {
        $this->middleware->prepend(...$middleware);
        return $this;
    }

    // HOOK API (BACKWARD COMPATIBLE) ////////////////////////////////////////////////////////

    /**
     * Add a hook to execute before each processor.
     *
     * @param callable(ProcessingState):mixed $hook
     */
    public function beforeEach(callable $hook): static {
        $this->hooks->add(CallBefore::with($hook));
        return $this;
    }

    /**
     * Add a hook to execute after each processor.
     *
     * @param callable(ProcessingState):mixed $hook
     */
    public function afterEach(callable $hook): static {
        $this->hooks->add(CallAfter::with($hook));
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
     * @param CanControlStateProcessing $hook
     */
    public function aroundEach(CanControlStateProcessing $hook): static {
        $this->hooks->add($hook);
        return $this;
    }

    /**
     * Add a condition to check if processing should finish early.
     *
     * @param callable(ProcessingState):bool $condition
     */
    public function finishWhen(callable $condition): static {
        $this->hooks->add(Skip::when($condition));
        return $this;
    }

    /**
     * Add a failure handler executed when any step fails.
     *
     * @param callable(ProcessingState):void $handler
     */
    public function onFailure(callable $handler): static {
        $this->hooks->add(TapOnFailure::with($handler));
        return $this;
    }

    /**
     * Fail the pipeline when a condition is met.
     *
     * @param callable(ProcessingState):bool $condition
     */
    public function failWhen(callable $condition, string $message = 'Condition failed'): static {
        $this->hooks->add(FailWhen::with($condition, $message));
        return $this;
    }

    // PROCESSING /////////////////////////////////////////////////////////////////////////////

    /**
     * @param array<callable(mixed):mixed> $callables
     */
    public function throughAll(callable ...$callables): static {
        foreach ($callables as $callable) {
            $this->through($callable);
        }
        return $this;
    }

    /**
     * @param callable(mixed):mixed $function
     */
    public function through(callable $function, NullStrategy $onNull = NullStrategy::Fail): static {
        $this->operators->add(Call::withValue($function)->onNull($onNull));
        return $this;
    }

    public function throughOperator(CanControlStateProcessing $operator): static {
        $this->operators->add($operator);
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
        $this->operators->add($operator);
        return $this;
    }

    /**
     * @param callable(mixed):void $callback
     */
    public function tap(callable $callback): static {
        $this->operators->add(Tap::withValue($callback));
        return $this;
    }

    /**
     * @param callable(ProcessingState):void $callback
     */
    public function tapWithState(callable $callback): static {
        $this->operators->add(Tap::withState($callback));
        return $this;
    }

    /**
     * Add transformation processor
     */
    public function map(callable $fn): static {
        return $this->through($fn);
    }

    public function filter(callable $condition, string $message = 'Value filter condition failed'): static {
        return $this->throughOperator(ConditionalCall::withValue($condition)->negate()->then(Fail::with($message)));
    }

    public function filterWithState(callable $condition, string $message = 'State filter condition failed'): static {
        return $this->throughOperator(ConditionalCall::withState($condition)->negate()->then(Fail::with($message)));
    }

    // EXECUTION //////////////////////////////////////////////////////////////////////////////

    /**
     * @param callable|CanControlStateProcessing(ProcessingState):mixed $finalizer
     */
    public function finally(callable|CanControlStateProcessing $finalizer): static {
        $finalizer = match (true) {
            $finalizer instanceof CanControlStateProcessing => $finalizer,
            is_callable($finalizer) => Call::withState($finalizer),
            default => throw new InvalidArgumentException('Finalizer must be callable or implement CanFinalizeProcessing'),
        };
        $this->finalizers->add($finalizer);
        return $this;
    }

    public function create(): PendingExecution {
        $pipeline = new Pipeline(
            processors: $this->operators,
            finalizers: $this->finalizers,
            middleware: $this->middleware,
            hooks: $this->hooks,
        );
        return new PendingExecution(
            ProcessingState::with(($this->source)(), $this->tags),
            $pipeline,
        );
    }
}

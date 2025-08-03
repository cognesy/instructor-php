<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Contracts\PipelineMiddlewareInterface;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\Finalizer\CallableFinalizer;
use Cognesy\Pipeline\Finalizer\FinalizerInterface;
use Cognesy\Pipeline\Middleware\CallAfter;
use Cognesy\Pipeline\Middleware\CallBefore;
use Cognesy\Pipeline\Middleware\CallOnFailure;
use Cognesy\Pipeline\Middleware\FailWhen;
use Cognesy\Pipeline\Middleware\PipelineMiddlewareStack;
use Cognesy\Pipeline\Middleware\SkipProcessing;
use Cognesy\Pipeline\Processor\CallWithValue;
use Cognesy\Pipeline\Processor\ConditionWithValue;
use Cognesy\Pipeline\Processor\FailOnConditionWithState;
use Cognesy\Pipeline\Processor\FailOnConditionWithValue;
use Cognesy\Pipeline\Processor\FlatMap;
use Cognesy\Pipeline\Processor\ProcessorStack;
use Cognesy\Pipeline\Processor\TapWithState;
use Cognesy\Pipeline\Processor\TapWithValue;
use Cognesy\Pipeline\Tag\TagInterface;
use Cognesy\Utils\Result\Failure;
use InvalidArgumentException;

class PipelineBuilder
{
    /** @var Closure():mixed $source */
    private Closure $source;
    /** @var array<TagInterface> */
    private array $tags;
    private ProcessorStack $processors;
    private FinalizerInterface $finalizer;
    private PipelineMiddlewareStack $middleware; // per-pipeline execution middleware stack
    private PipelineMiddlewareStack $hooks; // per-processor execution hooks

    /**
     * @param ?callable():mixed $source
     */
    public function __construct(
        ?callable $source = null,
        ?array $tags = null,
    ) {
        $this->source = $source ?? fn() => null;
        $this->tags = $tags ?? [];
        $this->processors = new ProcessorStack();
        $this->finalizer = new CallableFinalizer(fn($data) => $data);
        $this->middleware = new PipelineMiddlewareStack();
        $this->hooks = new PipelineMiddlewareStack();
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
    public function withMiddleware(PipelineMiddlewareInterface ...$middleware): static {
        $this->middleware->add(...$middleware);
        return $this;
    }

    /**
     * Add middleware at the beginning of the stack (executes first).
     */
    public function prependMiddleware(PipelineMiddlewareInterface ...$middleware): static {
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
     * Add a condition to check if processing should finish early.
     *
     * @param callable(ProcessingState):bool $condition
     */
    public function finishWhen(callable $condition): static {
        $this->hooks->add(SkipProcessing::with($condition));
        return $this;
    }

    /**
     * Add a failure handler executed when any step fails.
     *
     * @param callable(Failure):void $handler
     */
    public function onFailure(callable $handler): static {
        $this->hooks->add(CallOnFailure::with($handler));
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
     * @param callable(mixed):mixed $processor
     */
    public function through(callable $processor, NullStrategy $onNull = NullStrategy::Fail): static {
        $this->processors->add(CallWithValue::fromCallable($processor, $onNull));
        return $this;
    }

    public function throughProcessor(CanProcessState $processor): static {
        $this->processors->add($processor);
        return $this;
    }

    /**
     * @param callable(mixed):bool $condition
     * @param callable(mixed):mixed $callback
     */
    public function when(callable $condition, callable $callback): static {
        $this->processors->add(new ConditionWithValue($condition, CallWithValue::fromCallable($callback)));
        return $this;
    }

    /**
     * @param callable(mixed):void $callback
     */
    public function tap(callable $callback): static {
        $this->processors->add(TapWithValue::fromCallable($callback));
        return $this;
    }

    /**
     * @param callable(ProcessingState):void $callback
     */
    public function tapWithState(callable $callback): static {
        $this->processors->add(TapWithState::fromCallable($callback));
        return $this;
    }

    /**
     * Add transformation processor
     */
    public function map(callable $fn): static {
        return $this->through($fn);
    }

    /**
     * Add flattening processor
     * @param callable(mixed):mixed $mapper
     */
    public function flatMap(callable $mapper): static {
        $this->processors->add(new FlatMap($mapper));
        return $this;
    }

    public function filter(callable $condition, string $message = 'Value filter condition failed'): static {
        return $this->throughProcessor(new FailOnConditionWithValue($condition, $message));
    }

    public function filterWithState(callable $condition, string $message = 'State filter condition failed'): static {
        return $this->throughProcessor(new FailOnConditionWithState($condition, $message));
    }

    /**
     * @param FinalizerInterface|callable(ProcessingState):mixed $finalizer
     */
    public function finally(callable|FinalizerInterface $finalizer): static {
        $this->finalizer = match (true) {
            $finalizer instanceof FinalizerInterface => $finalizer,
            is_callable($finalizer) => new CallableFinalizer($finalizer),
            default => throw new InvalidArgumentException('Finalizer must be callable or implement FinalizerInterface'),
        };
        return $this;
    }

    // EXECUTION //////////////////////////////////////////////////////////////////////////////

    public function create(): PendingExecution {
        $pipeline = new Pipeline(
            $this->processors,
            $this->finalizer,
            $this->middleware,
            $this->hooks,
        );
        return new PendingExecution(
            ProcessingState::with(($this->source)(), $this->tags),
            $pipeline,
        );
    }
}
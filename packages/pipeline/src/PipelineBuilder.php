<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Closure;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\Finalizer\CallableFinalizer;
use Cognesy\Pipeline\Finalizer\FinalizerInterface;
use Cognesy\Pipeline\Middleware\CallAfterMiddleware;
use Cognesy\Pipeline\Middleware\CallBeforeMiddleware;
use Cognesy\Pipeline\Middleware\CallOnFailureMiddleware;
use Cognesy\Pipeline\Middleware\ConditionalMiddleware;
use Cognesy\Pipeline\Middleware\PipelineMiddlewareInterface;
use Cognesy\Pipeline\Middleware\PipelineMiddlewareStack;
use Cognesy\Pipeline\Processor\ConditionalValueProcessor;
use Cognesy\Pipeline\Processor\ProcessorInterface;
use Cognesy\Pipeline\Processor\ProcessorStack;
use Cognesy\Pipeline\Processor\TapProcessor;
use Cognesy\Pipeline\Processor\ValueProcessor;
use Cognesy\Pipeline\Tag\TagInterface;
use InvalidArgumentException;

class PipelineBuilder {
    private Closure $source;
    private array $tags;
    private ProcessorStack $processors;
    private FinalizerInterface $finalizer;
    private PipelineMiddlewareStack $middleware; // per-pipeline execution middleware stack
    private PipelineMiddlewareStack $hooks; // per-processor execution hooks

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
     */
    public function beforeEach(callable $hook): static {
        $this->hooks->add(CallBeforeMiddleware::call($hook));
        return $this;
    }

    /**
     * Add a hook to execute after each processor.
     */
    public function afterEach(callable $hook): static {
        $this->hooks->add(CallAfterMiddleware::call($hook));
        return $this;
    }

    /**
     * Add a condition to check if processing should finish early.
     *
     * Implemented as ConditionalMiddleware with skipRemaining=true.
     */
    public function finishWhen(callable $condition): static {
        $this->hooks->add(ConditionalMiddleware::finishWhen($condition));
        return $this;
    }

    /**
     * Add a failure handler executed when any step fails.
     *
     * Implemented as CallOnFailureMiddleware for consistency.
     */
    public function onFailure(callable $handler): static {
        $this->hooks->add(CallOnFailureMiddleware::call($handler));
        return $this;
    }

    // PROCESSING /////////////////////////////////////////////////////////////////////////////

    public function throughAll(callable ...$callables): static {
        foreach ($callables as $callable) {
            $this->through($callable);
        }
        return $this;
    }

    public function through(callable $processor, NullStrategy $onNull = NullStrategy::Fail): static {
        $this->processors->add(ValueProcessor::from($processor, $onNull));
        return $this;
    }

    public function throughProcessor(ProcessorInterface $processor): static {
        $this->processors->add($processor);
        return $this;
    }

    public function when(callable $condition, callable $callback): static {
        $this->processors->add(new ConditionalValueProcessor($condition, ValueProcessor::from($callback)));
        return $this;
    }

    public function tap(callable $processor): static {
        $this->processors->add(new TapProcessor(ValueProcessor::from($processor)));
        return $this;
    }

    public function finally(callable|FinalizerInterface $finalizer): static {
        $this->finalizer = match(true) {
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
            $this->hooks
        );
        return new PendingExecution(
            ProcessingState::with(($this->source)(), $this->tags),
            $pipeline,
        );
    }
}
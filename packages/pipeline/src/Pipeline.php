<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Closure;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\Middleware\AddTagsMiddleware;
use Cognesy\Pipeline\Middleware\CallAfterMiddleware;
use Cognesy\Pipeline\Middleware\CallBeforeMiddleware;
use Cognesy\Pipeline\Middleware\CallOnFailureMiddleware;
use Cognesy\Pipeline\Middleware\ConditionalMiddleware;
use Cognesy\Pipeline\Middleware\PipelineMiddlewareInterface;
use Cognesy\Pipeline\Middleware\PipelineMiddlewareStack;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Pipeline\Tag\TagInterface;
use Cognesy\Pipeline\Tag\TagMap;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Exception;
use Generator;
use ReflectionFunction;
use Throwable;

/**
 * Pipeline with per-execution & per-step middleware support.
 */
class Pipeline
{
    private ?Closure $source;
    private array $processors = [];
    private ?Closure $finalizer = null;
    private PipelineMiddlewareStack $middleware; // per-pipeline execution middleware stack
    private PipelineMiddlewareStack $hooks; // per-processor execution hooks

    public function __construct(
        ?callable $source = null,
        array $processors = [],
        ?callable $finalizer = null,
    ) {
        $this->source = $source;
        $this->processors = $processors;
        $this->finalizer = $finalizer;
        $this->middleware = new PipelineMiddlewareStack();
        $this->hooks = new PipelineMiddlewareStack();
    }

    // STATIC FACTORY METHODS ////////////////////////////////////////////////////////////////

    public static function make(): static {
        return new static();
    }

    public static function from(callable $source): static {
        return new static(source: $source);
    }

    /**
     * Create a new MessageChain instance with an initial value.
     *
     * This is a convenience method that creates a source callable that returns the value.
     */
    public static function for(mixed $value): static {
        return new static(source: fn() => $value);
    }

    // CONFIGURATION //////////////////////////////////////////////////////////////////////////

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
     * Add tags to be included in the computation during processing.
     */
    public function withTag(TagInterface ...$tags): static {
        $this->middleware->add(AddTagsMiddleware::with(...$tags));
        return $this;
    }

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

    public function throughAll(callable ...$processors): static {
        foreach ($processors as $processor) {
            $this->processors[] = $this->suspendExecution($processor, NullStrategy::Allow);
        }
        return $this;
    }

    public function through(callable $processor, NullStrategy $onNull = NullStrategy::Fail): static {
        $this->processors[] = $this->suspendExecution($processor, $onNull);
        return $this;
    }

    public function when(callable $condition, callable $callback): static {
        $this->processors[] = $this->suspendConditionalExecution($condition, $callback, NullStrategy::Allow);
        return $this;
    }

    public function tap(callable $processor): static {
        $this->processors[] = $this->suspendSideEffectExecution($processor, NullStrategy::Allow);
        return $this;
    }

    public function finally(?callable $callback = null): static {
        $this->finalizer = $callback;
        return $this;
    }

    // EXECUTION //////////////////////////////////////////////////////////////////////////////

    public function process(mixed $value = null, array $tags = []): PendingExecution {
        return new PendingExecution(function () use ($value, $tags) {
            $initialValue = $value ?? $this->getSourceValue();
            $computation = $this->createInitialComputation($initialValue, $tags);
            // Apply middleware around entire processor chain
            $processedComputation = $this->middleware->isEmpty()
                ? $this->applyProcessors($computation)
                : $this->middleware->process($computation, fn($comp) => $this->applyProcessors($comp));

            return $this->applyFinalizer($this->finalizer, $processedComputation);
        });
    }

    public function stream(iterable $stream): Generator {
        foreach ($stream as $item) {
            yield $this->process($item);
        }
    }

    // INTERNAL IMPLEMENTATION ///////////////////////////////////////////////////////////////

    private function getSourceValue(): mixed {
        if ($this->source === null) {
            throw new \InvalidArgumentException('Pipeline requires either an explicit value or a source callable');
        }
        return ($this->source)();
    }

    private function createInitialComputation(mixed $value, array $tags = []): Computation {
        if ($value instanceof Computation) {
            return empty($tags) ? $value : $value->with(...$tags);
        }
        return new Computation(
            $this->asResult($value),
            TagMap::create($tags),
        );
    }

    private function suspendExecution(callable $processor, NullStrategy $onNull): callable {
        return function (Computation $computation) use ($processor, $onNull) {
            return $this->executeProcessor($processor, $computation, $onNull);
        };
    }

    private function suspendConditionalExecution(callable $condition, callable $callback, NullStrategy $onNull): callable {
        return function (Computation $computation) use ($condition, $callback, $onNull) {
            if ($condition($computation)) {
                return $this->executeProcessor($callback, $computation, $onNull);
            }
            return $computation;
        };
    }

    private function suspendSideEffectExecution(callable $processor, NullStrategy $onNull) : callable {
        return function (Computation $computation) use ($processor, $onNull) {
            $this->executeProcessor($processor, $computation, $onNull);
            return $computation;
        };
    }

    private function executeProcessor(callable $processor, Computation $computation, NullStrategy $onNull): Computation {
        // If no per processor hooks, execute processor directly (backward compatibility)
        if ($this->hooks->isEmpty()) {
            return $this->executeProcessorDirect($processor, $computation, $onNull);
        }
        // Execute per processor hooks
        return $this->hooks->process($computation, function (Computation $computation) use ($processor, $onNull) {
            return $this->executeProcessorDirect($processor, $computation, $onNull);
        });
    }

    private function executeProcessorDirect(callable $processor, Computation $computation, NullStrategy $onNull): Computation {
        if (!$this->shouldContinueProcessing($computation)) {
            return $computation; // Short-circuit on existing failure
        }
        try {
            return match (true) {
                $this->isComputationProcessor($processor) => $this->asComputation($processor($computation), $computation, $onNull),
                default => $this->asComputation($processor($computation->result()->unwrap()), $computation, $onNull),
            };
        } catch (Exception $e) {
            return $this->handleProcessorError($computation, $e);
        }
    }

    private function isComputationProcessor(callable $processor): bool {
        if (is_array($processor)) {
            throw new \InvalidArgumentException('Array callable processors are not supported. Use a Closure or function instead.');
        }
        try {
            $reflection = new ReflectionFunction($processor);
            $parameters = $reflection->getParameters();
            if (empty($parameters)) {
                return false;
            }
            $firstParam = $parameters[0];
            $type = $firstParam->getType();
            return $type && $type->getName() === Computation::class;
        } catch (\Throwable) {
            return false;
        }
    }

    private function applyProcessors(Computation $computation): Computation {
        $current = $computation;
        foreach ($this->processors as $processor) {
            $result = $processor($current);
            if (!$this->shouldContinueProcessing($result)) {
                return $result;
            }
            $current = $result;
        }
        return $current;
    }

    private function applyFinalizer(?callable $finalizer, Computation $computation): Computation {
        if ($finalizer === null) {
            return $computation;
        }
        try {
            $value = match (true) {
                $this->isComputationProcessor($finalizer) => $finalizer($computation),
                default => $finalizer($computation->result()),
            };
        } catch (Exception $e) {
            return $this->handleProcessorError($computation, $e);
        }
        return $this->asComputation($value, $computation, NullStrategy::Allow);
    }

    private function asResult(mixed $value, NullStrategy $onNull = NullStrategy::Allow): Result {
        return match (true) {
            $value instanceof Result => $value,
            $value === null && NullStrategy::Fail->is($onNull) => Result::failure(new Exception('Value cannot be null')),
            $value === null && NullStrategy::Allow->is($onNull) => Result::success(null),
            default => Result::success($value),
        };
    }

    private function asComputation(mixed $value, Computation $computation, NullStrategy $onNull): Computation {
        return match (true) {
            $value === null => $this->handleNullResult($computation, $onNull),
            $value instanceof Computation => $value,
            $value instanceof Result => $computation->withResult($value),
            default => $computation->withResult(Result::success($value)),
        };
    }

    private function handleNullResult(Computation $computation, NullStrategy $onNull = NullStrategy::Fail): Computation {
        return match ($onNull) {
            NullStrategy::Skip => $computation,
            NullStrategy::Fail => $this->createFailureComputation($computation, "Processor returned null value"),
            NullStrategy::Allow => $computation->withResult(Result::success(null)),
        };
    }

    /**
     * Determines if pipeline processing should continue based on computation state.
     *
     * @param Computation $computation Current computation to check
     * @return bool True if processing should continue, false to short-circuit
     */
    private function shouldContinueProcessing(Computation $computation): bool {
        return $computation->result()->isSuccess();
    }

    /**
     * Handles processor errors by converting them to failure computations.
     *
     * Consolidates error-to-Result conversion, ErrorTag creation, and
     * computation wrapping into a single, focused method.
     *
     * @param Computation $computation Current computation context
     * @param mixed $error Error to handle (Exception, string, or other)
     * @return Computation Failure computation with error Result and ErrorTag
     */
    private function handleProcessorError(Computation $computation, mixed $error): Computation {
        // Convert error to Result::failure
        $failure = match (true) {
            $error instanceof Result => $error,
            $error instanceof Throwable => Result::failure($error),
            is_string($error) => Result::failure(new Exception($error)),
            default => Result::failure(new Exception(json_encode(['error' => $error]))),
        };

        // Convert error to ErrorTag
        $errorTag = match (true) {
            $error instanceof Exception => ErrorTag::fromException($error),
            default => ErrorTag::fromMessage((string)$error),
        };

        return $computation
            ->withResult($failure)
            ->with($errorTag);
    }

    private function createFailureComputation(Computation $computation, mixed $error): Computation {
        return $computation
            ->withResult($this->asFailure($error))
            ->with($this->asErrorTag($error));
    }

    private function asFailure(mixed $error): Failure {
        return match (true) {
            $error instanceof Result => $error,
            $error instanceof Throwable => Result::failure($error),
            is_string($error) => Result::failure(new Exception($error)),
            default => Result::failure(new Exception(json_encode(['error' => $error]))),
        };
    }

    private function asErrorTag(mixed $error): ErrorTag {
        return match (true) {
            $error instanceof Exception => ErrorTag::fromException($error),
            default => ErrorTag::fromMessage((string)$error),
        };
    }
}
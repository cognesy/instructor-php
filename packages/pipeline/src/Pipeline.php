<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Closure;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\Middleware\AddStampsMiddleware;
use Cognesy\Pipeline\Middleware\CallAfterMiddleware;
use Cognesy\Pipeline\Middleware\CallBeforeMiddleware;
use Cognesy\Pipeline\Middleware\CallOnFailureMiddleware;
use Cognesy\Pipeline\Middleware\ConditionalMiddleware;
use Cognesy\Pipeline\Stamps\ErrorStamp;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Exception;
use ReflectionFunction;
use Throwable;

/**
 * Pipeline with middleware support.
 *
 * This version provides both the familiar hook-based API and the more powerful
 * middleware pattern for advanced use cases. Hooks are implemented as middleware
 * under the hood, providing a seamless upgrade path.
 *
 * Features:
 * - Middleware support for advanced cross-cutting concerns
 * - Automatic conversion of hooks to middleware
 * - Composable and reusable middleware components
 *
 * Example migration:
 * ```php
 * // Old hook style (still works)
 * $pipeline
 *     ->beforeEach(fn($env) => $env->with(new TimestampStamp()))
 *     ->afterEach(fn($env) => logger()->info($env->getResult()->unwrap()));
 *
 * // New middleware style (more powerful)
 * $pipeline->withMiddleware(
 *     new TimestampMiddleware(),
 *     new LoggingMiddleware(logger())
 * );
 *
 * // Mixed approach (hooks + middleware)
 * $pipeline
 *     ->beforeEach(fn($env) => $env->with(new TimestampStamp()))
 *     ->withMiddleware(new DistributedTracingMiddleware($tracer))
 *     ->afterEach(fn($env) => logger()->info($env->getResult()->unwrap()));
 * ```
 */
class Pipeline
{
    private mixed $source;
    private array $processors = [];
    private ?Closure $finalizer = null;
    private PipelineMiddlewareStack $middleware;

    public function __construct(
        ?callable $source = null,
        array $processors = [],
        ?callable $finalizer = null,
    ) {
        $this->source = $source;
        $this->processors = $processors;
        $this->finalizer = $finalizer;
        $this->middleware = new PipelineMiddlewareStack();
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
     * Add stamps to be included in the envelope during processing.
     */
    public function withStamp(StampInterface ...$stamps): static
    {
        $this->middleware->add(AddStampsMiddleware::with(...$stamps));
        return $this;
    }

    /**
     * Add a hook to execute before each processor.
     */
    public function beforeEach(callable $hook): static {
        $this->middleware->add(CallBeforeMiddleware::call($hook));
        return $this;
    }

    /**
     * Add a hook to execute after each processor.
     */
    public function afterEach(callable $hook): static {
        $this->middleware->add(CallAfterMiddleware::call($hook));
        return $this;
    }

    /**
     * Add a condition to check if processing should finish early.
     *
     * Implemented as ConditionalMiddleware with skipRemaining=true.
     */
    public function finishWhen(callable $condition): static {
        $this->middleware->add(ConditionalMiddleware::finishWhen($condition));
        return $this;
    }

    /**
     * Add a failure handler executed when any step fails.
     *
     * Implemented as CallOnFailureMiddleware for consistency.
     */
    public function onFailure(callable $handler): static {
        $this->middleware->add(CallOnFailureMiddleware::call($handler));
        return $this;
    }

    // PROCESSING /////////////////////////////////////////////////////////////////////////////

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

    public function then(?callable $callback = null): static {
        $this->finalizer = $callback;
        return $this;
    }

    // EXECUTION //////////////////////////////////////////////////////////////////////////////

    public function process(mixed $value = null, array $stamps = []): PendingPipelineExecution {
        return new PendingPipelineExecution(function () use ($value, $stamps) {
            $initialValue = $value ?? $this->getSourceValue();
            $envelope = $this->createInitialEnvelope($initialValue, $stamps);
            $processedEnvelope = $this->applyProcessors($envelope);
            return $this->applyFinalizer($this->finalizer, $processedEnvelope);
        });
    }

    public function stream(iterable $stream): \Generator {
        foreach ($stream as $item) {
            yield $this->process($item);
        }
    }

    // INTERNAL IMPLEMENTATION ///////////////////////////////////////////////////////////////

    private function getSourceValue(): mixed {
        if ($this->source === null) {
            throw new \InvalidArgumentException('MessageChain requires either an explicit value or a source callable');
        }
        return ($this->source)();
    }

    private function createInitialEnvelope(mixed $value, array $stamps = []): Envelope {
        // Handle direct Envelope input
        if ($value instanceof Envelope) {
            return empty($stamps) ? $value : $value->with(...$stamps);
        }
        return new Envelope(
            $this->asResult($value),
            $this->indexStamps($stamps)
        );
    }

    private function suspendExecution(callable $processor, NullStrategy $onNull): callable {
        return function (Envelope $envelope) use ($processor, $onNull) {
            return $this->executeProcessor($processor, $envelope, $onNull);
        };
    }

    private function suspendConditionalExecution(callable $condition, callable $callback, NullStrategy $onNull): callable {
        return function (Envelope $envelope) use ($condition, $callback, $onNull) {
            if ($condition($envelope)) {
                return $this->executeProcessor($callback, $envelope, $onNull);
            }
            return $envelope;
        };
    }

    private function suspendSideEffectExecution(callable $processor, NullStrategy $Allow) : callable {
        return function (Envelope $envelope) use ($processor) {
            $this->executeProcessor($processor, $envelope, NullStrategy::Allow);
            return $envelope;
        };
    }

    private function executeProcessor(callable $processor, Envelope $envelope, NullStrategy $onNull): Envelope {
        // If no middleware, execute processor directly (backward compatibility)
        if ($this->middleware->isEmpty()) {
            return $this->executeProcessorDirect($processor, $envelope, $onNull);
        }
        // Execute processor through middleware stack
        return $this->middleware->process($envelope, function (Envelope $env) use ($processor, $onNull) {
            return $this->executeProcessorDirect($processor, $env, $onNull);
        });
    }

    private function executeProcessorDirect(callable $processor, Envelope $envelope, NullStrategy $onNull): Envelope {
        if ($envelope->getResult()->isFailure()) {
            return $envelope; // Short-circuit on existing failure
        }
        try {
            return match (true) {
                $this->isEnvelopeProcessor($processor) => $this->asEnvelope($processor($envelope), $envelope, $onNull),
                default => $this->asEnvelope($processor($envelope->getResult()->unwrap()), $envelope, $onNull),
            };
        } catch (Exception $e) {
            return $this->createFailureEnvelope($envelope, $e);
        }
    }

    private function isEnvelopeProcessor(callable $processor): bool {
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
            return $type && $type->getName() === Envelope::class;
        } catch (\Throwable) {
            return false;
        }
    }

    private function applyProcessors(Envelope $envelope): Envelope {
        $current = $envelope;

        foreach ($this->processors as $processor) {
            $result = $processor($current);

            // Handle failure
            if ($result->getResult()->isFailure()) {
                return $result;
            }

            $current = $result;
        }

        return $current;
    }

    private function applyFinalizer(?callable $finalizer, Envelope $envelope): Envelope {
        if ($finalizer === null) {
            return $envelope;
        }
        try {
            $value = match (true) {
                $this->isEnvelopeProcessor($finalizer) => $finalizer($envelope),
                default => $finalizer($envelope->getResult()),
            };
        } catch (Exception $e) {
            return $this->createFailureEnvelope($envelope, $e);
        }
        return $this->asEnvelope($value, $envelope, NullStrategy::Allow);
    }

    private function indexStamps(array $stamps): array {
        $indexed = [];
        foreach ($stamps as $stamp) {
            $class = $stamp::class;
            $indexed[$class] = $indexed[$class] ?? [];
            $indexed[$class][] = $stamp;
        }
        return $indexed;
    }

    private function asResult(mixed $payload, NullStrategy $onNull = NullStrategy::Allow): Result {
        return match (true) {
            $payload instanceof Result => $payload,
            $payload === null && NullStrategy::Fail->is($onNull) => Result::failure(new Exception('Value cannot be null')),
            $payload === null && NullStrategy::Allow->is($onNull) => Result::success(null),
            default => Result::success($payload),
        };
    }

    private function asEnvelope(mixed $payload, Envelope $envelope, NullStrategy $onNull): Envelope {
        return match (true) {
            $payload === null => $this->handleNullResult($envelope, $onNull),
            $payload instanceof Envelope => $payload,
            $payload instanceof Result => $envelope->withMessage($payload),
            default => $envelope->withMessage(Result::success($payload)),
        };
    }

    private function handleNullResult(Envelope $envelope, NullStrategy $onNull = NullStrategy::Fail): Envelope {
        return match ($onNull) {
            NullStrategy::Skip => $envelope,
            NullStrategy::Fail => $this->createFailureEnvelope($envelope, "Processor returned null value"),
            NullStrategy::Allow => $envelope->withMessage(Result::success(null)),
        };
    }

    private function createFailureEnvelope(Envelope $envelope, mixed $error): Envelope {
        return $envelope
            ->withMessage($this->asFailure($error))
            ->with($this->asErrorStamp($error));
    }

    private function asFailure(mixed $error): Failure {
        return match (true) {
            $error instanceof Result => $error,
            $error instanceof Throwable => Result::failure($error),
            is_string($error) => Result::failure(new Exception($error)),
            default => Result::failure(new Exception(json_encode(['error' => $error]))),
        };
    }

    private function asErrorStamp(mixed $error): ErrorStamp {
        return match (true) {
            $error instanceof Exception => ErrorStamp::fromException($error),
            default => ErrorStamp::fromMessage((string)$error),
        };
    }
}
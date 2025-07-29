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
 *     ->afterEach(fn($env) => logger()->info($env->result()->unwrap()));
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
 *     ->afterEach(fn($env) => logger()->info($env->result()->unwrap()));
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
     * Create a new MessageChain instance with an initial payload.
     * 
     * This is a convenience method that creates a source callable that returns the payload.
     */
    public static function for(mixed $payload): static {
        return new static(source: fn() => $payload);
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

    public function then(?callable $callback = null): static {
        $this->finalizer = $callback;
        return $this;
    }

    // EXECUTION //////////////////////////////////////////////////////////////////////////////

    public function process(mixed $payload = null, array $stamps = []): PendingPipelineExecution {
        return new PendingPipelineExecution(function () use ($payload, $stamps) {
            $initialPayload = $payload ?? $this->getSourcePayload();
            $envelope = $this->createInitialEnvelope($initialPayload, $stamps);
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

    private function getSourcePayload(): mixed {
        if ($this->source === null) {
            throw new \InvalidArgumentException('MessageChain requires either an explicit payload or a source callable');
        }
        return ($this->source)();
    }

    private function createInitialEnvelope(mixed $payload, array $stamps = []): Envelope {
        if ($payload instanceof Envelope) {
            return empty($stamps) ? $payload : $payload->with(...$stamps);
        }
        return new Envelope(
            $this->asResult($payload),
            StampMap::create($stamps)
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
        if (!$this->shouldContinueProcessing($envelope)) {
            return $envelope; // Short-circuit on existing failure
        }
        try {
            return match (true) {
                $this->isEnvelopeProcessor($processor) => $this->asEnvelope($processor($envelope), $envelope, $onNull),
                default => $this->asEnvelope($processor($envelope->result()->unwrap()), $envelope, $onNull),
            };
        } catch (Exception $e) {
            return $this->handleProcessorError($envelope, $e);
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

            // Check if processing should continue
            if (!$this->shouldContinueProcessing($result)) {
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
            $payload = match (true) {
                $this->isEnvelopeProcessor($finalizer) => $finalizer($envelope),
                default => $finalizer($envelope->result()),
            };
        } catch (Exception $e) {
            return $this->handleProcessorError($envelope, $e);
        }
        return $this->asEnvelope($payload, $envelope, NullStrategy::Allow);
    }


    private function asResult(mixed $payload, NullStrategy $onNull = NullStrategy::Allow): Result {
        return match (true) {
            $payload instanceof Result => $payload,
            $payload === null && NullStrategy::Fail->is($onNull) => Result::failure(new Exception('Payload cannot be null')),
            $payload === null && NullStrategy::Allow->is($onNull) => Result::success(null),
            default => Result::success($payload),
        };
    }

    private function asEnvelope(mixed $payload, Envelope $envelope, NullStrategy $onNull): Envelope {
        return match (true) {
            $payload === null => $this->handleNullResult($envelope, $onNull),
            $payload instanceof Envelope => $payload,
            $payload instanceof Result => $envelope->withResult($payload),
            default => $envelope->withResult(Result::success($payload)),
        };
    }

    private function handleNullResult(Envelope $envelope, NullStrategy $onNull = NullStrategy::Fail): Envelope {
        return match ($onNull) {
            NullStrategy::Skip => $envelope,
            NullStrategy::Fail => $this->createFailureEnvelope($envelope, "Processor returned null payload"),
            NullStrategy::Allow => $envelope->withResult(Result::success(null)),
        };
    }

    /**
     * Determines if pipeline processing should continue based on envelope state.
     * 
     * @param Envelope $envelope Current envelope to check
     * @return bool True if processing should continue, false to short-circuit
     */
    private function shouldContinueProcessing(Envelope $envelope): bool
    {
        return $envelope->result()->isSuccess();
    }

    /**
     * Handles processor errors by converting them to failure envelopes.
     * 
     * Consolidates error-to-Result conversion, ErrorStamp creation, and 
     * envelope wrapping into a single, focused method.
     * 
     * @param Envelope $envelope Current envelope context
     * @param mixed $error Error to handle (Exception, string, or other)
     * @return Envelope Failure envelope with error Result and ErrorStamp
     */
    private function handleProcessorError(Envelope $envelope, mixed $error): Envelope
    {
        // Convert error to Result::failure
        $failure = match (true) {
            $error instanceof Result => $error,
            $error instanceof Throwable => Result::failure($error),
            is_string($error) => Result::failure(new Exception($error)),
            default => Result::failure(new Exception(json_encode(['error' => $error]))),
        };

        // Convert error to ErrorStamp
        $errorStamp = match (true) {
            $error instanceof Exception => ErrorStamp::fromException($error),
            default => ErrorStamp::fromMessage((string)$error),
        };

        return $envelope
            ->withResult($failure)
            ->with($errorStamp);
    }

    private function createFailureEnvelope(Envelope $envelope, mixed $error): Envelope {
        return $envelope
            ->withResult($this->asFailure($error))
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
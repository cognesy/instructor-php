<?php declare(strict_types=1);

namespace Cognesy\Utils\Chain;

use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\Result\Success;
use Exception;

/**
 * Processes a chain of operations on a value or a source of values.
 *
 * The chain is defined by a series of processors that are applied to the
 * value, which is being transformed through the chain.
 *
 * Each processor is a callback that takes the value of the previous
 * processor or the initial value as an argument and returns the
 * result of the processing.
 *
 * The chain can be created with an initial value, a source of values, or
 * left empty.
 *
 * The chain is Result aware, meaning that processors can return a Result
 * object, which will be unwrapped before passing to the next processor
 * and then processed. It allows you to stop processing the chain by returning
 * a Result::failure() object.
 *
 * When any step produces Result::failure(), the chain will stop processing
 * and execute the onFailure handlers. The chain will then skip to the finalizer
 * callback with the failure result.
 *
 * The chain can be configured with a context value that is passed to each
 * processor. The chain can also be configured with a finalizer callback
 * that is executed after all processors have been applied.
 *
 * The chain can be configured with failure handlers that are executed when
 * a processor returns a failure result. They cannot modify the result, but
 * can perform side effects based on the failure.
 *
 * NOTE: Returning null from a processor will stop further processing,
 * and the chain will skip to the finalizer callback with the last result.
 *
 * @package Cognesy\Utils
 */
class ResultChain
{
    public const BREAK_ON_NULL = 'break';
    public const FAIL_ON_NULL = 'fail';
    public const CONTINUE_ON_NULL = 'continue';

    private ?Result $carry;
    private mixed $source;
    private array $onFailure = [];
    private array $processors;
    private mixed $finalizer;
    private mixed $context;

    public function __construct(
        ?Result $value = null,
        ?callable $source = null,
        mixed $context = null,
        array $processors = [],
        ?callable $finalizer = null,
    ) {
        $this->carry = $value;
        $this->source = $source;
        $this->context = $context;
        $this->processors = $processors;
        $this->finalizer = $finalizer;
    }

    // CREATION ///////////////////////////////////////////////////////////////////////////////

    /**
     * Create a new ResultChain instance.
     *
     * @return ResultChain
     */
    static public function make() : static {
        return new ResultChain();
    }

    /**
     * Create a new ResultChain instance with an initial value.
     *
     * @param mixed $value
     * @return ResultChain
     */
    static public function for(mixed $value): static {
        return new ResultChain(value: Result::from($value));
    }

    /**
     * Create a new ResultChain instance with a source of values.
     *
     * @param callable $source
     * @return ResultChain
     */
    static public function from(callable $source): static {
        return new ResultChain(source: $source);
    }

    // DEFINITION /////////////////////////////////////////////////////////////////////////////

    /**
     * Set the initial value of the chain.
     *
     * @param mixed $value
     * @return ResultChain
     */
    public function withContext(mixed $context): static {
        $this->context = $context;
        return $this;
    }

    /**
     * Add a processors to the chain.
     *
     * The processors are executed on the value, and the result is passed
     * to the next processor in the chain.
     *
     * @param callable $processor - a processor callback
     * @param string $onNull - how to handle null values: FAIL_ON_NULL (default), BREAK_ON_NULL, CONTINUE_ON_NULL
     * @return ResultChain
     */
    public function through(array|callable $processors, string $onNull = self::FAIL_ON_NULL): static {
        // ensure processors is an array
        if (is_callable($processors)) {
            $processors = [$processors];
        }
        // add processors to the chain
        foreach ($processors as $processor) {
            $this->processors[] = function($value) use ($processor, $onNull) {
                return $this->asResult($this->processValue($processor, $value), $onNull);
            };
        }
        return $this;
    }

    /**
     * Add a conditional processor to the chain.
     *
     * The processor is executed on the value only if the condition
     * callback returns true.
     *
     * If the condition returns true, the chain continues with the result of
     * the provided processor callback.
     *
     * Otherwise, the chain continues with the original value.
     *
     * @param callable $condition - a condition callback, must return a boolean
     * @param callable $callback
     * @return ResultChain
     */
    public function when(callable $condition, callable $callback): static {
        $this->processors[] = function($value) use ($condition, $callback) {
            if ($condition($value, $this->context)) {
                return $this->asResult($this->processValue($callback, $value));
            }
            return $value;
        };
        return $this;
    }

    /**
     * Add a tap processor to the chain.
     *
     * The processor is executed on the value, but the chain continues
     * with the original value.
     *
     * @param callable $processor
     * @return ResultChain
     */
    public function tap(callable $processor): static {
        $this->processors[] = function($value) use ($processor) {
            // ignore $result - tap() is transparent to the chain
            $this->processValue($processor, $value);
            return $value;
        };
        return $this;
    }

    /**
     * Add a failure handler to the chain.
     *
     * The handler is executed when a processor returns a failure result.
     *
     * @param callable $handler<Failure>
     * @return ResultChain
     */
    public function onFailure(callable $handler) : static {
        $this->onFailure[] = $handler;
        return $this;
    }

    /**
     * Add a finalizer callback to the chain.
     *
     * The finalizer is executed after all processors have been applied.
     *
     * @param callable|null $callback<Result>
     * @return ResultChain
     */
    public function then(?callable $callback = null): static {
        $this->finalizer = $callback;
        return $this;
    }

    // EXECUTION //////////////////////////////////////////////////////////////////////////////

    /**
     * Process the chain and return the result - unwrapped or Result object.
     *
     * @param mixed|null $value - the initial value
     * @param bool $unwrap - whether to unwrap the result to raw value
     * @return mixed - the result of the processing
     */
    public function process(mixed $value = null, bool $unwrap = true) : mixed {
        $carry = $value ?? $this->getCarry();
        $chainResult = $this->applyProcessors($carry);
        $thenResult = $this->applyThen($this->finalizer, $chainResult);
        return $this->wrapOrUnwrap($thenResult, !$unwrap);
    }

    /**
     * Process a stream of values via the chain.
     *
     * Retrieves subsequent values from the provided iterable and processes
     * each of them through the defined processors.
     *
     * After processing of each value, it is yielded to the caller.
     *
     * The final result of the processing can be processed by the finalizer
     * callback (if it has been provided) and also yielded to the caller.
     *
     * @param iterable $stream
     * @param bool $unwrap
     * @return iterable
     */
    public function stream(iterable $stream, bool $unwrap = true): iterable {
        $chainResult = Result::success(true);
        foreach ($stream as $partial) {
            $chainResult = $this->applyProcessors($partial);
            yield $this->wrapOrUnwrap($chainResult, !$unwrap);
        }
        $thenResult = $this->applyThen($this->finalizer, $chainResult);
        yield $this->wrapOrUnwrap($thenResult, !$unwrap);
    }

    /**
     * Process the chain and return Result object
     *
     * @param callable|null $callback
     * @return Result
     */
    public function result(?callable $callback = null): Result {
        return $this->process($callback, false);
    }

    // INTERNAL ///////////////////////////////////////////////////////////////////////////////

    /**
     * Get the initial carry from the source or the provided value.
     *
     * @return mixed
     */
    private function getCarry() : mixed {
        return match(true) {
            $this->source !== null => ($this->source)(),
            default => $this->carry,
        };
    }

    /**
     * Process the value with the processor.
     *
     * @param callable $processor
     * @param mixed $value
     * @return mixed
     */
    private function processValue(callable $processor, mixed $value): mixed {
        return match(true) {
            $value instanceof Result => $processor($value->unwrap()),
            ($value === null) => $processor(),
            default => $processor($value),
        };
    }

    /**
     * Wrap the value into a Result object or unwrap it if it is already a Result.
     *
     * @param mixed $value
     * @param string $onNull
     * @return Result|null
     */
    private function asResult(mixed $value, string $onNull = self::FAIL_ON_NULL) : ?Result {
        return match(true) {
            $value instanceof Result => $value,
            ($value === null) => match($onNull) {
                self::BREAK_ON_NULL => null,
                self::FAIL_ON_NULL => Result::failure("Processor returned null value"),
                self::CONTINUE_ON_NULL => Result::success(null),
            },
            default => Result::success($value),
        };
    }

    /**
     * Wrap or unwrap the value based on the flag.
     * @param mixed $value
     * @param bool $wrapped
     * @return mixed
     */
    private function wrapOrUnwrap(mixed $value, bool $wrapped = true): mixed {
        return match(true) {
            $wrapped => $this->asResult($value, self::CONTINUE_ON_NULL),
            default => $this->unwrapIfResult($value),
        };
    }

    /**
     * Unwrap the value if it is a Result object.
     *
     * @param mixed $value
     * @return mixed
     */
    private function unwrapIfResult(mixed $value): mixed {
        return match(true) {
            $value instanceof Success => $value->unwrap(),
            $value instanceof Failure => null,
            default => $value,
        };
    }

    /**
     * Apply all processors to the value.
     *
     * @param mixed $value
     * @return mixed
     */
    private function applyProcessors(mixed $value) : mixed {
        foreach ($this->processors as $processor) {
            $result = $this->tryProcess($processor, $value);
            // if processor has returned null - break the chain
            if ($result === null) {
                break;
            }
            // if chain step has failed - execute failure handlers...
            // ...and break the chain: skip to then() callback
            if ($result->isFailure()) {
                $this->applyFailureHandlers($result);
                return $result;
            }
            $value = $result;
        }
        return $value;
    }

    /**
     * Try applying a processor to the value catching any exceptions.
     *
     * @param mixed $processor
     * @param mixed $value
     * @return mixed
     */
    private function tryProcess(mixed $processor, mixed $value) : mixed {
        try {
            $result = $processor($value);
        } catch (Exception $e) {
            $result = Result::failure($e);
        }
        return $result;
    }

    /**
     * Apply the then final callback to the processing result.
     *
     * @param callable|null $callback
     * @param Result $value
     * @return mixed
     */
    private function applyThen(?callable $callback, Result $value): mixed {
        return match(true) {
            ($callback !== null) => $callback($value),
            default => $value,
        };
    }

    /**
     * Apply failure handlers in case of failure.
     *
     * @param Failure $result
     * @return void
     */
    private function applyFailureHandlers(Failure $value) : void {
        foreach ($this->onFailure as $handler) {
            $handler($value);
        }
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Legacy\Chain;

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
    /** @var callable():mixed|null */
    private $source;
    /** @var list<callable(Failure):void> */
    private array $onFailure = [];
    /** @var list<callable> */
    private array $processors;
    /** @var callable(Result):mixed|null */
    private $finalizer;
    private mixed $context;

    /**
     * @param callable():mixed|null $source
     * @param callable(Result):mixed|null $finalizer
     */
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
     */
    static public function make(): static {
        return new static();
    }

    /**
     * Create a new ResultChain instance with an initial value.
     */
    static public function for(mixed $input): static {
        return new static(value: Result::from($input));
    }

    /**
     * Create a new ResultChain instance with a source of values.
     *
     * @param callable():mixed $source
     */
    static public function from(callable $source): static {
        return new static(source: $source);
    }

    // DEFINITION /////////////////////////////////////////////////////////////////////////////

    /**
     * Set the initial value of the chain.
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
     * @param callable(mixed):mixed|list<callable(mixed):mixed> $processors - a processor callback or array of callbacks
     * @param string $onNull - how to handle null values: FAIL_ON_NULL (default), BREAK_ON_NULL, CONTINUE_ON_NULL
     */
    public function through(array|callable $processors, string $onNull = self::FAIL_ON_NULL): static {
        // ensure processors is an array
        if (is_callable($processors)) {
            $processors = [$processors];
        }
        // add processors to the chain
        foreach ($processors as $processor) {
            $this->processors[] = function ($value) use ($processor, $onNull) {
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
     * @param callable(mixed,mixed):bool $condition - a condition callback, must return a boolean
     * @param callable(mixed):mixed $callback
     */
    public function when(callable $condition, callable $callback): static {
        $this->processors[] = function ($value) use ($condition, $callback) {
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
     * @param callable(mixed):mixed $processor
     */
    public function tap(callable $processor): static {
        $this->processors[] = function ($value) use ($processor) {
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
     * @param callable(Failure):void $handler
     */
    public function onFailure(callable $handler): static {
        $this->onFailure[] = $handler;
        return $this;
    }

    /**
     * Add a finalizer callback to the chain.
     *
     * The finalizer is executed after all processors have been applied.
     *
     * @param callable(Result):mixed|null $callback
     */
    public function then(?callable $callback = null): static {
        $this->finalizer = $callback;
        return $this;
    }

    // EXECUTION //////////////////////////////////////////////////////////////////////////////

    /**
     * Process the chain and return the result - unwrapped or Result object.
     *
     * @param mixed|null $input - the initial value
     * @param bool $unwrap - whether to unwrap the result to raw value
     * @return mixed - the result of the processing
     */
    public function process(mixed $input = null, bool $unwrap = true): mixed {
        $carry = $input ?? $this->getCarry();
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
     * @param iterable<mixed> $stream
     * @param bool $unwrap
     * @return iterable<mixed>
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
     * @param callable(Result):mixed|null $callback
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
    private function getCarry(): mixed {
        return match (true) {
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
        return match (true) {
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
    private function asResult(mixed $value, string $onNull = self::FAIL_ON_NULL): ?Result {
        return match (true) {
            $value instanceof Result => $value,
            ($value === null) => match ($onNull) {
                self::BREAK_ON_NULL => null,
                self::FAIL_ON_NULL => Result::failure("Processor returned null value"),
                self::CONTINUE_ON_NULL => Result::success(null),
            },
            default => Result::success($value),
        };
    }

    /**
     * Wrap or unwrap the value based on the flag.
     *
     * @param mixed $any
     * @param bool $wrapped
     * @return mixed
     */
    private function wrapOrUnwrap(mixed $any, bool $wrapped = true): mixed {
        return match (true) {
            $wrapped => $this->asResult($any, self::CONTINUE_ON_NULL),
            default => $this->unwrapIfResult($any),
        };
    }

    /**
     * Unwrap the value if it is a Result object.
     *
     * @param mixed $any
     * @return mixed
     */
    private function unwrapIfResult(mixed $any): mixed {
        return match (true) {
            $any instanceof Success => $any->unwrap(),
            $any instanceof Failure => null,
            default => $any,
        };
    }

    /**
     * Apply all processors to the value.
     *
     * @param mixed $input
     * @return mixed
     */
    private function applyProcessors(mixed $input): mixed {
        foreach ($this->processors as $processor) {
            $output = $this->tryProcess($processor, $input);
            // if processor has returned null - break the chain
            if ($output === null) {
                break;
            }
            // if chain step has failed - execute failure handlers...
            // ...and break the chain: skip to then() callback
            if ($output->isFailure()) {
                $this->applyFailureHandlers($output);
                return $output;
            }
            $input = $output;
        }
        return $input;
    }

    /**
     * Try applying a processor to the value catching any exceptions.
     *
     * @param mixed $processor
     * @param mixed $input
     * @return mixed
     */
    private function tryProcess(mixed $processor, mixed $input): mixed {
        try {
            $result = $processor($input);
        } catch (Exception $e) {
            $result = Result::failure($e);
        }
        return $result;
    }

    /**
     * Apply the then final callback to the processing result.
     *
     * @param callable(Result):mixed|null $callback
     * @param Result $result
     * @return mixed
     */
    private function applyThen(?callable $callback, Result $result): mixed {
        return match (true) {
            ($callback !== null) => $callback($result),
            default => $result,
        };
    }

    /**
     * Apply failure handlers in case of failure.
     *
     * @param Failure $failure
     * @return void
     */
    private function applyFailureHandlers(Failure $failure): void {
        foreach ($this->onFailure as $handler) {
            $handler($failure);
        }
    }
}

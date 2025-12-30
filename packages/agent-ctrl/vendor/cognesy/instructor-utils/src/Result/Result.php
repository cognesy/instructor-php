<?php declare(strict_types=1);

namespace Cognesy\Utils\Result;

use Cognesy\Utils\Exceptions\CompositeException;
use Exception;
use Throwable;

/**
 * A Result class that encapsulates a value or an error.
 *
 * @template T The type of the value in case of success.
 * @template E The type of the error in case of failure.
 */
abstract readonly class Result
{
    // Constructors & Factories ////////////////////////////////////////////

    public static function from(mixed $value) : Result {
        return match(true) {
            $value instanceof Result => $value,
            $value instanceof Throwable => self::failure($value),
            default => self::success($value),
        };
    }

    public static function success(mixed $value): Success {
        return new Success($value);
    }

    public static function failure(mixed $error): Failure {
        return new Failure($error);
    }

    /**
     * @template R The return type of the callable.
     * @param callable():R $callable The callable to execute.
     * @return Result<R, Throwable> A Result instance representing the outcome of the callable execution.
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement - Success/Failure are valid Result implementations
     */
    public static function try(callable $callable): Result {
        try {
            return self::success($callable());
        } catch (Throwable $e) {
            return self::failure($e);
        }
    }

    /**
     * Execute all callbacks and collect results and errors.
     *
     * @template R
     * @param array<array-key, mixed> $args Arguments spread into each callback
     * @param callable(mixed ...): R ...$callbacks Callbacks invoked with spread arguments
     * @return Result<list<R>|null, Throwable> Success with list of results (or null if none), or CompositeException
     */
    public static function tryAll(array $args, callable ...$callbacks): Result {
        $errors = [];
        $results = [];
        foreach ($callbacks as $callback) {
            try {
                $results[] = $callback(...$args);
            } catch (Throwable $e) {
                $errors[] = $e;
            }
        }
        return match (true) {
            !empty($errors) => Result::failure(new CompositeException($errors)),
            !empty($results) => Result::success($results),
            default => Result::success(null),
        };
    }

    /**
     * Execute callbacks until condition returns true for a result.
     *
     * @template R
     * @param callable(R): bool $condition Predicate to test each callback result
     * @param array<array-key, mixed> $args Arguments spread into each callback
     * @param callable(mixed ...): R ...$callbacks Callbacks invoked with spread arguments
     * @return Result<R|false, Throwable> First matching result or false if none; errors aggregated
     */
    public static function tryUntil(callable $condition, array $args, callable ...$callbacks): Result {
        $errors = [];
        foreach($callbacks as $callback) {
            try {
                $result = $callback(...$args);
                if ($condition($result)) {
                    return Result::success($result);
                }
            } catch (Throwable $e) {
                $errors[] = $e;
            }
        }
        return match (true) {
            !empty($errors) => Result::failure(new CompositeException($errors)),
            default => Result::success(false),
        };
    }

    // State Inspection ////////////////////////////////////////////////////

    abstract public function isSuccess(): bool;
    abstract public function isFailure(): bool;

    // Value Access (implemented in subclasses) ////////////////////////////

    /**
     * Get the success value (only available on Success instances)
     * @return T
     */
    abstract public function unwrap(): mixed;

    /**
     * Get the error value (only available on Failure instances)
     * @return E
     */
    abstract public function error(): mixed;

    /**
     * Get the error as an exception (only available on Failure instances)
     */
    abstract public function exception(): Throwable;

    public function isSuccessAndNull(): bool {
        return $this->isSuccess()
            && ($this->unwrap() === null);
    }

    public function isSuccessAndTrue() : bool {
        return $this->isSuccess()
            && ($this->unwrap() === true);
    }

    public function isSuccessAndFalse() : bool {
        return $this->isSuccess()
            && ($this->unwrap() === false);
    }

    public function isType(string $type): bool {
        return $this->isSuccess() && gettype($this->unwrap()) === $type;
    }

    public function isInstanceOf(string $class): bool {
        return $this->isSuccess() && $this->unwrap() instanceof $class;
    }

    /**
     * @param callable(T):bool $predicate
     */
    public function matches(callable $predicate): bool {
        return $this->isSuccess() && $predicate($this->unwrap());
    }

    // Result Value Access /////////////////////////////////////////////////

    public function valueOr(mixed $default): mixed {
        return $this->isSuccess() ? $this->unwrap() : $default;
    }

    public function exceptionOr(mixed $default): mixed {
        return $this->isFailure() ? $this->exception() : $default;
    }


    // Success-Side Transformations ////////////////////////////////////////

    /**
     * @template S
     * @param callable(T):(Result<S, E>|S) $f Function to apply to the value in case of success
     * @return Result A new Result instance with the function applied, or the original failure
     * @psalm-return Result<S, E>
     * @phpstan-return Result
     */
    public function then(callable $f): Result {
        if ($this->isFailure()) {
            // Return a fresh Failure to avoid template-invariance issues when returning $this
            return self::failure($this->error());
        }
        try {
            $value = $this->unwrap();
            $result = $f($value);
            return $result instanceof Result
                ? $result
                : self::success($result);
        } catch (Exception $e) {
            return self::failure($e);
        }
    }

    /**
     * @template S
     * @param callable(T):S $f Function to apply to the value in case of success
     * @return Result<S, E> A new Result instance with the transformed value, or the original failure
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement - Functor mapping preserves Result type
     */
    public function map(callable $f): Result {
        if ($this->isSuccess()) {
            try {
                $output = $f($this->unwrap());
                return match (true) {
                    $output instanceof Result => $output,
                    is_null($output) => self::success(null),
                    default => self::success($output),
                };
            } catch (Exception $e) {
                return self::failure($e);
            }
        }
        return $this;
    }

    /**
     * This method ensures that a predicate holds for the success value.
     * If the predicate fails, it transforms the Result into a Failure using the provided error factory.
     *
     * @template ENew
     * @param callable(T):bool $predicateFn Guard evaluated when the result is successful
     * @param callable(T):(ENew|Result<T, ENew>) $onFailureFn Error factory executed when the predicate fails
     * @return Result
     * @psalm-return Result<T, E|ENew>
     * @phpstan-return Result
     */
    public function ensure(callable $predicateFn, callable $onFailureFn): Result {
        if ($this->isFailure()) {
            // Return a fresh Failure to avoid template-invariance issues when returning $this
            return self::failure($this->error());
        }

        try {
            $value = $this->unwrap();
            if ($predicateFn($value)) {
                return $this;
            }
            $failure = $onFailureFn($value);
            return $failure instanceof Result
                ? $failure
                : self::failure($failure);
        } catch (Exception $e) {
            return self::failure($e);
        }
    }

    /**
     * @param callable(T):void $sideEffect Callback executed when the result is successful
     * @return Result<T, E>
     */
    public function tap(callable $sideEffect): Result {
        if ($this->isFailure()) {
            return $this;
        }

        try {
            $sideEffect($this->unwrap());
            return $this;
        } catch (Exception $e) {
            return self::failure($e);
        }
    }


    // Failure-Side Transformations ////////////////////////////////////////

    /**
     * @template F
     * @param callable(E):F $f Function to recover from the error in case of failure
     * @return Result<T, F> A new Result instance with the recovery applied, or the original success
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement - Error recovery transforms error type
     */
    public function recover(callable $f): Result {
        if ($this->isFailure()) {
            try {
                return self::success($f($this->error()));
            } catch (Exception $e) {
                return self::failure($e);
            }
        }
        return $this;
    }

    /**
     * @template ENew
     * @param callable(E):(ENew|Result<T, ENew>) $f Transformer applied to the error when the result is a failure
     * @return Result<T, ENew>
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement - Error mapping transforms error type
     */
    public function mapError(callable $f): Result {
        if ($this->isSuccess()) {
            return $this;
        }

        try {
            $error = $f($this->error());
            return $error instanceof Result
                ? $error
                : self::failure($error);
        } catch (Exception $e) {
            return self::failure($e);
        }
    }


    // Side-Effect Hooks ///////////////////////////////////////////////////

    /**
     * @param callable(T):void $callback
     */
    public function ifSuccess(callable $callback): self {
        if ($this->isSuccess()) {
            $callback($this->unwrap());
        }
        return $this;
    }

    /**
     * @param callable(Throwable):void $callback
     */
    public function ifFailure(callable $callback): self {
        if ($this->isFailure()) {
            $callback($this->exception());
        }
        return $this;
    }
}

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
     */
    public static function try(callable $callable): Result {
        try {
            return self::success($callable());
        } catch (Throwable $e) {
            return self::failure($e);
        }
    }

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
     * @param callable(T):Result<S, E> $f Function to apply to the value in case of success
     * @return Result<S, E> A new Result instance with the function applied, or the original failure
     */
    public function then(callable $f): Result {
        return $this->map(function($value) use ($f) {
            $result = $f($value);
            return $result instanceof Result
                ? $result
                : self::success($result);
        });
    }

    /**
     * @template S
     * @param callable(T):S $f Function to apply to the value in case of success
     * @return Result<S, E> A new Result instance with the transformed value, or the original failure
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
     * @template ENew
     * @param callable(T):bool $predicate Guard evaluated when the result is successful
     * @param callable(T):ENew|Result<T, ENew> $onFailure Error factory executed when the predicate fails
     * @return Result<T, E|ENew>
     */
    public function ensure(callable $predicate, callable $onFailure): Result {
        if ($this->isFailure()) {
            return $this;
        }

        try {
            $value = $this->unwrap();
            if ($predicate($value)) {
                return $this;
            }
            $failure = $onFailure($value);
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
     * @param callable(E):ENew|Result<T, ENew> $f Transformer applied to the error when the result is a failure
     * @return Result<T, ENew>
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

    public function ifSuccess(callable $callback): self {
        if ($this->isSuccess()) {
            $callback($this->unwrap());
        }
        return $this;
    }

    public function ifFailure(callable $callback): self {
        if ($this->isFailure()) {
            $callback($this->exception());
        }
        return $this;
    }
}

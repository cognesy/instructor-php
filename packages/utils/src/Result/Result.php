<?php
namespace Cognesy\Utils\Result;

use Exception;

//////////////////////////////////////////////////////////////////////////////////////////////////
//
//    /**
//     * Tries to perform an operation that can either succeed with an integer result
//     * or fail with an error message.
//     *
//     * @return Result<int, string> Result object encapsulating either a success value or an error.
//     */
//    function performOperation(): Result {
//        // Some operation...
//        if ($success) {
//            return Result::success(42); // Success with an integer value
//        } else {
//            return Result::failure("An error occurred"); // Failure with a string error message
//        }
//    }
//
//    $result = performOperation();
//
//    // Execute if operation succeeded, if not - continue with the error
//    $result = $result->then(function (int $value): string {
//        return "Transformed value: " . ($value * 2);
//    });
//
//    if ($result->isSuccess()) {
//        // IDE should suggest `unwrap` method and understand its return type is `int`
//        $value = $result->unwrap();
//        echo "Operation succeeded with result: $value";
//    } elseif ($result->isFailure()) {
//        // IDE should suggest `error` method and understand its return type is `string`
//        $error = $result->error();
//        echo "Operation failed with error: $error";
//    }
//
//    // Transforming the result if operation succeeded
//    $transformedResult = $result->try(function (int $value): string {
//        return "Transformed value: " . ($value * 2);
//    });
//
/////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * @template T The type of the value in case of success.
 * @template E The type of the error in case of failure.
 */
abstract class Result {
    public static function with(mixed $value) : Result {
        if ($value instanceof Result) {
            return $value;
        }
        return new Success($value);
    }

    public static function success(mixed $value): Success {
        return new Success($value);
    }

    public static function failure(mixed $error): Failure {
        return new Failure($error);
    }

    abstract public function isSuccess(): bool;
    abstract public function isFailure(): bool;

    /**
     * @template S
     * @param callable(T):Result<S, E> $f Function to apply to the value in case of success
     * @return Result<S, E> A new Result instance with the function applied, or the original failure
     */
    public function then(callable $f): Result {
        if ($this->isSuccess()) {
            try {
                $result = $f($this->unwrap());
                return match(true) {
                    $result instanceof Result => $result,
                    default => self::success($result),
                };
            } catch (Exception $e) {
                return self::failure($e);
            }
        }
        return $this;
    }

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
     * @template R The return type of the callable.
     * @param callable():R $callable The callable to execute.
     * @return Result<R, Exception> A Result instance representing the outcome of the callable execution.
     */
    public static function try(callable $callable): Result {
        try {
            return self::success($callable());
        } catch (Exception $e) {
            return self::failure($e);
        }
    }

    /**
     * @return bool True if the result is a success and not null.
     */
    public function isNull(): bool {
        return $this->isSuccess()
            && ($this->unwrap() === null);
    }
}

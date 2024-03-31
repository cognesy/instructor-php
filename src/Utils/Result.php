<?php
namespace Cognesy\Instructor\Utils;

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
//            return Result::success(42); // Assuming success with an integer value
//        } else {
//            return Result::failure("An error occurred"); // Assuming failure with a string error message
//        }
//    }
//
//    $result = performOperation();
//
//    if ($result->isSuccess()) {
//        // IDE should suggest `getValue` method and understand its return type is `int`
//        $value = $result->getValue();
//        echo "Operation succeeded with result: $value";
//    } elseif ($result->isFailure()) {
//        // IDE should suggest `getError` method and understand its return type is `string`
//        $error = $result->getError();
//        echo "Operation failed with error: $error";
//    }
//
//    // Transforming the result if operation succeeded
//    $transformedResult = $result->try(function (int $value): string {
//        return "Transformed value: " . ($value * 2);
//    });
//
//    // Handling error, transforming it into a default value
//    $defaultValue = $transformedResult->catch(function (string $error): string {
//        return "Default value due to error: $error";
//    });
//
/////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * @template T The type of the value in case of success.
 * @template E The type of the error in case of failure.
 */
abstract class Result {
    public static function with(mixed $value) : Success {
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
}

/**
 * @template T
 * @extends Result<T, mixed>
 */
class Success extends Result {
    /**
     * @var T
     */
    private $value;

    /**
     * @param T $value The success value
     */
    public function __construct($value) {
        $this->value = $value;
    }

    /**
     * @return T
     */
    public function unwrap() {
        return $this->value;
    }

    public function isSuccess(): bool {
        return true;
    }

    public function isFailure(): bool {
        return false;
    }
}

/**
 * @template E
 * @extends Result<mixed, E>
 */
class Failure extends Result {
    /**
     * @var E
     */
    private mixed $error;

    /**
     * @param E $error The error value
     */
    public function __construct($error) {
        $this->error = $error;
    }

    /**
     * @return E
     */
    public function error() : mixed {
        return $this->error;
    }

    public function errorMessage() : string {
        if (is_a($this->error, Exception::class)) {
            return $this->error->getMessage();
        }
        return (string) $this->error;
    }

    public function isSuccess(): bool {
        return false;
    }

    public function isFailure(): bool {
        return true;
    }
}

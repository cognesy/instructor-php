<?php declare(strict_types=1);

namespace Cognesy\Utils\Result;

use Cognesy\Utils\Exceptions\CompositeException;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * @template E
 * @extends Result<mixed, E>
 */
final readonly class Failure extends Result {
    /** @var E */
    private mixed $error;

    /** @param E $error The error value */
    public function __construct($error) {
        $this->error = $error;
    }

    /** @return E */
    public function error() : mixed {
        return $this->error;
    }

    public function errorMessage() : string {
        return $this->toMessage($this->error);
    }

    public function exception() : Throwable {
        return $this->toException($this->error);
    }

    public function isSuccess(): bool {
        return false;
    }

    public function isFailure(): bool {
        return true;
    }

    // PRIVATE

    private function toMessage(mixed $error): string {
        return match(true) {
            is_string($error) => $error,
            $error instanceof Throwable => $error->getMessage(),
            $error instanceof Stringable => $error->__toString(),
            method_exists($error, '__toString') => (string) $error,
            method_exists($error, 'toString') => $error->toString(),
            method_exists($error, 'toArray') => json_encode($error->toArray()),
            default => json_encode($error, JSON_THROW_ON_ERROR),
        };
    }

    private function toException(mixed $error): Throwable {
        return match(true) {
            $error instanceof Throwable => $error,
            is_string($error) => new RuntimeException($error),
            is_array($error) => new CompositeException($error),
            default => new RuntimeException($this->toMessage($error)),
        };
    }
}

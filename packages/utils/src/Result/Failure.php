<?php declare(strict_types=1);

namespace Cognesy\Utils\Result;

use Cognesy\Utils\Exceptions\CompositeException;
use RuntimeException;
use Throwable;

/**
 * @template E
 * @extends Result<mixed, E>
 */
final class Failure extends Result {
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
        return match(true) {
            $this->error instanceof Throwable => $this->error->getMessage(),
            is_string($this->error) => $this->error,
            is_array($this->error) => json_encode($this->error),
            default => (string) $this->error,
        };
    }

    public function exception() : Throwable {
        return match(true) {
            $this->error instanceof Throwable => $this->error,
            is_string($this->error) => new RuntimeException($this->error),
            is_array($this->error) => new CompositeException($this->error),
            default => new RuntimeException($this->errorMessage()),
        };
    }

    public function isSuccess(): bool {
        return false;
    }

    public function isFailure(): bool {
        return true;
    }
}

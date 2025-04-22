<?php
namespace Cognesy\Utils\Result;

use Throwable;

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
        return match(true) {
            $this->error instanceof Throwable => $this->error->getMessage(),
            default => (string) $this->error,
        };
    }

    public function isSuccess(): bool {
        return false;
    }

    public function isFailure(): bool {
        return true;
    }
}

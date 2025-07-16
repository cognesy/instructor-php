<?php declare(strict_types=1);

namespace Cognesy\Utils\Result;

use Exception;
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

    public function exception() : Throwable {
        if ($this->error instanceof Throwable) {
            return $this->error;
        }
        throw new Exception($this->errorMessage());
    }

    public function isSuccess(): bool {
        return false;
    }

    public function isFailure(): bool {
        return true;
    }
}

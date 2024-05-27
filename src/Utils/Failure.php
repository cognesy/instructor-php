<?php
namespace Cognesy\Instructor\Utils;

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

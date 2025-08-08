<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Query;

use Cognesy\Utils\Result\Result;

/**
 * Fluent query interface for Result operations.
 */
final class ResultQuery
{
    public function __construct(
        private readonly Result $result
    ) {}

    // TERMINAL OPERATIONS

    public function value(): mixed {
        return $this->result->unwrap();
    }

    public function get(): Result {
        return $this->result;
    }

    // FLUENT OPERATIONS

    public function ifSuccess(callable $callback): self {
        if ($this->result->isSuccess()) {
            $callback($this->result->unwrap());
        }
        return $this;
    }

    public function ifFailure(callable $callback): self {
        if ($this->result->isFailure()) {
            $callback($this->result->errorMessage(), $this->result->exception());
        }
        return $this;
    }
}
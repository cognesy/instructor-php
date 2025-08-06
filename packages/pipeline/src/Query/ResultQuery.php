<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Query;

use Cognesy\Utils\Result\Result;
use Throwable;

/**
 * Fluent query interface for Result operations.
 */
final class ResultQuery
{
    public function __construct(private readonly Result $result) {}

    public function value(): mixed {
        return $this->result->unwrap();
    }

    public function get(): Result {
        return $this->result;
    }

    public function valueOr(mixed $default): mixed {
        return $this->result->isSuccess() ? $this->result->unwrap() : $default;
    }

    public function isSuccess(): bool {
        return $this->result->isSuccess();
    }

    public function isFailure(): bool {
        return $this->result->isFailure();
    }

    public function errorMessage(): string {
        return $this->result->errorMessage();
    }

    public function exception(): ?Throwable {
        return $this->result->exception();
    }

    public function exceptionOr(?Throwable $default): ?Throwable {
        return $this->result->exception() ?? $default;
    }

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

    public function isType(string $type): bool {
        return $this->result->isSuccess() && gettype($this->result->unwrap()) === $type;
    }

    public function isInstanceOf(string $class): bool {
        return $this->result->isSuccess() && $this->result->unwrap() instanceof $class;
    }

    public function matches(callable $predicate): bool {
        return $this->result->isSuccess() && $predicate($this->result->unwrap());
    }
}
<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Core;

/**
 * Result of parsing - either success or failure.
 * Immutable, allows backtracking.
 */
final readonly class ParseResult
{
    private function __construct(
        public bool $success,
        public mixed $value,
        public ParserState $state,
        public ?string $error = null,
    ) {}

    public static function success(mixed $value, ParserState $state): self
    {
        return new self(
            success: true,
            value: $value,
            state: $state,
            error: null,
        );
    }

    public static function failure(string $error, ParserState $state): self
    {
        return new self(
            success: false,
            value: null,
            state: $state,
            error: $error,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * Get the parsed value (throws if failed).
     */
    public function getValue(): mixed
    {
        if ($this->isFailure()) {
            throw new \RuntimeException("Cannot get value from failed parse: {$this->error}");
        }
        return $this->value;
    }

    /**
     * Get error message (throws if succeeded).
     */
    public function getError(): string
    {
        if ($this->isSuccess()) {
            throw new \RuntimeException("Cannot get error from successful parse");
        }
        return $this->error;
    }

    /**
     * Map the value if successful.
     */
    public function map(callable $fn): self
    {
        if ($this->isFailure()) {
            return $this;
        }

        return self::success(
            value: $fn($this->value),
            state: $this->state,
        );
    }

    /**
     * FlatMap (bind) for chaining parsers.
     */
    public function flatMap(callable $fn): self
    {
        if ($this->isFailure()) {
            return $this;
        }

        return $fn($this->value, $this->state);
    }
}

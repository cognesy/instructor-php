<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use Cognesy\Utils\Exceptions\ErrorList;

/** Immutable public-safe collection of errors recorded by an execution. */
final readonly class TellExecutionErrors
{
    /** @var list<TellExecutionError> */
    private array $items;

    public function __construct(TellExecutionError ...$items) {
        $this->items = $items;
    }

    public static function empty(): self {
        return new self();
    }

    public static function fromErrors(ErrorList $errors): self {
        return new self(...array_map(
            TellExecutionError::fromThrowable(...),
            $errors->all(),
        ));
    }

    public function count(): int {
        return count($this->items);
    }

    public function isEmpty(): bool {
        return $this->items === [];
    }

    /** @return list<TellExecutionError> */
    public function all(): array {
        return array_values($this->items);
    }

    /** @return list<array{code: string, category: string, phase: string, message: string}> */
    public function toArray(): array {
        return array_values(array_map(
            static fn (TellExecutionError $error): array => $error->toArray(),
            $this->items,
        ));
    }
}

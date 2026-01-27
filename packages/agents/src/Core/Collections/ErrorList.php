<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Collections;

use Cognesy\Agents\Core\Exceptions\ToolExecutionException;
use Throwable;

/**
 * Immutable collection of step or tool execution errors.
 */
final readonly class ErrorList
{
    /** @var list<Throwable> */
    private array $items;

    public function __construct(Throwable ...$items)
    {
        $this->items = $items;
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @param array<int, mixed> $errors
     */
    public static function fromArray(array $errors): self
    {
        $items = [];
        foreach ($errors as $error) {
            $normalized = self::normalize($error);
            if ($normalized === null) {
                continue;
            }
            $items[] = $normalized;
        }
        return new self(...$items);
    }

    public function hasAny(): bool
    {
        return $this->items !== [];
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function first(): ?Throwable
    {
        return $this->items[0] ?? null;
    }

    /**
     * @return list<Throwable>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function withAppended(Throwable ...$errors): self
    {
        return new self(...[...$this->items, ...$errors]);
    }

    public function toMessagesString(): string
    {
        if ($this->items === []) {
            return '';
        }
        return implode("\n", array_map(
            static fn(Throwable $error): string => $error->getMessage(),
            $this->items,
        ));
    }

    /**
     * @param mixed $error
     */
    private static function normalize(mixed $error): ?Throwable
    {
        if ($error instanceof Throwable) {
            return $error;
        }

        if (!is_array($error)) {
            return null;
        }

        $message = match (true) {
            isset($error['message']) && is_string($error['message']) => $error['message'],
            default => 'Unknown tool-use error',
        };
        $class = match (true) {
            isset($error['class']) && is_string($error['class']) => $error['class'],
            default => ToolExecutionException::class,
        };

        if (!is_a($class, Throwable::class, true)) {
            return new ToolExecutionException($message);
        }

        return self::instantiate($class, $message);
    }

    /**
     * @param class-string<Throwable> $class
     */
    private static function instantiate(string $class, string $message): Throwable
    {
        return new $class($message);
    }
}

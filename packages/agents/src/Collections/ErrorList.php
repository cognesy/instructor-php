<?php declare(strict_types=1);

namespace Cognesy\Agents\Collections;

use Cognesy\Agents\Exceptions\ToolExecutionException;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Throwable;

/**
 * Immutable collection of step or tool execution errors.
 */
final readonly class ErrorList
{
    /** @var list<Throwable> */
    private array $items;

    public function __construct(Throwable ...$items) {
        $this->items = $items;
    }

    public static function empty(): self {
        return new self();
    }

    public static function with(Throwable ...$errors): self {
        return new self(...$errors);
    }

    // ACCESSORS ////////////////////////////////////////////

    public function hasAny(): bool {
        return $this->items !== [];
    }

    public function hasError(string $className): bool {
        foreach ($this->items as $item) {
            if (is_a($item, $className, true)) {
                return true;
            }
        }
        return false;
    }

    public function isEmpty(): bool {
        return $this->items === [];
    }

    public function count(): int {
        return count($this->items);
    }

    public function first(): ?Throwable {
        return $this->items[0] ?? null;
    }

    /**
     * @return list<Throwable>
     */
    public function all(): array {
        return $this->items;
    }

    // MUTATORS /////////////////////////////////////////////

    public function withAppendedExceptions(Throwable ...$errors): self {
        return new self(...[...$this->items, ...$errors]);
    }

    public function withMergedErrorList(ErrorList $errors) : self {
        return new self(...[...$this->items, ...$errors->items]);
    }

    public function toMessagesString(): string {
        if ($this->items === []) {
            return '';
        }
        return implode("\n", array_map(
            static fn(Throwable $error): string => $error->getMessage(),
            $this->items,
        ));
    }

    /**
     * @param array<int, mixed> $errors
     */
    public static function fromArray(array $errors): self {
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

    private static function normalize(mixed $error): ?Throwable {
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
            return new ToolExecutionException($message, ToolCall::none());
        }
        return self::instantiate($class, $message);
    }

    /**
     * @param class-string<Throwable> $class
     */
    private static function instantiate(string $class, string $message): Throwable {
        return new $class($message);
    }
}

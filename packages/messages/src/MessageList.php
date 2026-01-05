<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

final readonly class MessageList implements Countable, IteratorAggregate
{
    /** @var Message[] */
    private array $messages;

    public function __construct(Message ...$messages) {
        $this->messages = $messages;
    }

    public static function empty(): self {
        return new self();
    }

    /** @param Message[] $messages */
    public static function fromArray(array $messages): self {
        return new self(...$messages);
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->messages);
    }

    /** @return Message[] */
    public function all(): array {
        return $this->messages;
    }

    public function count(): int {
        return count($this->messages);
    }

    public function isEmpty(): bool {
        return $this->messages === [];
    }

    public function first(): ?Message {
        return $this->messages[0] ?? null;
    }

    public function last(): ?Message {
        $index = array_key_last($this->messages);
        return match (true) {
            $index === null => null,
            default => $this->messages[$index],
        };
    }

    public function get(int $index): ?Message {
        return $this->messages[$index] ?? null;
    }

    public function add(Message $message): self {
        $messages = $this->messages;
        $messages[] = $message;
        return new self(...$messages);
    }

    public function addAll(self $messages): self {
        return new self(...array_merge($this->messages, $messages->messages));
    }

    public function prependAll(self $messages): self {
        return new self(...array_merge($messages->messages, $this->messages));
    }

    public function removeHead(): self {
        if ($this->messages === []) {
            return $this;
        }
        $messages = $this->messages;
        array_shift($messages);
        return new self(...$messages);
    }

    public function removeTail(): self {
        if ($this->messages === []) {
            return $this;
        }
        $messages = $this->messages;
        array_pop($messages);
        return new self(...$messages);
    }

    public function replaceLast(Message $message): self {
        if ($this->messages === []) {
            return new self($message);
        }
        $messages = $this->messages;
        $messages[array_key_last($messages)] = $message;
        return new self(...$messages);
    }

    public function reversed(): self {
        return new self(...array_reverse($this->messages));
    }

    /** @return array<mixed> */
    public function map(callable $callback): array {
        return array_map($callback, $this->messages);
    }

    public function reduce(callable $callback, mixed $initial = null): mixed {
        return array_reduce($this->messages, $callback, $initial);
    }

    public function filter(callable $callback): self {
        return new self(...array_values(array_filter($this->messages, $callback)));
    }

    public function withoutEmpty(): self {
        return $this->filter(fn(Message $message) => !$message->isEmpty());
    }

    /** @return array<array<array-key, mixed>> */
    public function toArray(): array {
        return array_map(
            fn(Message $message) => $message->toArray(),
            $this->messages,
        );
    }
}

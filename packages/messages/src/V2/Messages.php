<?php declare(strict_types=1);

namespace Cognesy\Messages\V2;

use ArrayIterator;
use Cognesy\Messages\Enums\MessageRole;
use Countable;
use Iterator;
use IteratorAggregate;

final readonly class Messages implements Countable, IteratorAggregate
{
    /** @var Message[] */
    private array $messages;

    private function __construct(Message ...$messages) {
        $this->messages = $messages;
    }

    // CREATION METHODS ///////////////////////////////////

    public static function create(Message ...$messages): self {
        return new self(...$messages);
    }

    public static function fromArray(array $data): self {
        $messages = array_map(
            fn($item) => is_array($item) ? Message::create(
                MessageRole::from($item['role'] ?? 'user'),
                $item['content'] ?? '',
                $item['id'] ?? null
            ) : $item,
            $data
        );
        return new self(...$messages);
    }

    public static function empty(): self {
        return new self();
    }

    // FLUENT API ////////////////////////////////////////

    public function asSystem(string|Content $content): self {
        return $this->add(Message::system($content));
    }

    public function asDeveloper(string|Content $content): self {
        return $this->add(Message::developer($content));
    }

    public function asUser(string|Content $content): self {
        return $this->add(Message::user($content));
    }

    public function asAssistant(string|Content $content): self {
        return $this->add(Message::assistant($content));
    }

    public function asTool(string|Content $content): self {
        return $this->add(Message::tool($content));
    }

    public function as(MessageRole $role, string|Content $content): self {
        return $this->add(Message::create($role, $content));
    }

    // SEQUENCE BUILDING /////////////////////////////////

    public function add(Message $message): self {
        return new self(...[...$this->messages, $message]);
    }

    public function addMultiple(Message ...$messages): self {
        return new self(...[...$this->messages, ...$messages]);
    }

    public function append(self $messages): self {
        return new self(...[...$this->messages, ...$messages->messages]);
    }

    public function prepend(self $messages): self {
        return new self(...[...$messages->messages, ...$this->messages]);
    }

    // CHAT UI MANIPULATION //////////////////////////////

    public function replaceMessage(string $id, Message $message): self {
        $newMessages = array_map(
            fn($msg) => $msg->id === $id ? $message : $msg,
            $this->messages
        );
        return new self(...$newMessages);
    }

    public function updateMessage(string $id, callable $updater): self {
        $newMessages = array_map(
            fn($msg) => $msg->id === $id ? $updater($msg) : $msg,
            $this->messages
        );
        return new self(...$newMessages);
    }

    public function removeMessage(string $id): self {
        $newMessages = array_filter(
            $this->messages,
            fn($msg) => $msg->id !== $id
        );
        return new self(...$newMessages);
    }

    public function insertAfter(string $id, Message $message): self {
        $newMessages = [];
        $inserted = false;
        foreach ($this->messages as $msg) {
            $newMessages[] = $msg;
            if ($msg->id === $id) {
                $newMessages[] = $message;
                $inserted = true;
            }
        }
        if (!$inserted) {
            $newMessages[] = $message;
        }
        return new self(...$newMessages);
    }

    public function insertBefore(string $id, Message $message): self {
        $newMessages = [];
        $inserted = false;
        foreach ($this->messages as $msg) {
            if ($msg->id === $id) {
                $newMessages[] = $message;
                $inserted = true;
            }
            $newMessages[] = $msg;
        }
        if (!$inserted) {
            $newMessages[] = $message;
        }
        return new self(...$newMessages);
    }

    public function truncateAfter(string $id): self {
        $newMessages = [];
        foreach ($this->messages as $msg) {
            $newMessages[] = $msg;
            if ($msg->id === $id) {
                break;
            }
        }
        return new self(...$newMessages);
    }

    public function truncateBefore(string $id): self {
        $newMessages = [];
        $found = false;
        foreach ($this->messages as $msg) {
            if ($msg->id === $id) {
                $found = true;
            }
            if ($found) {
                $newMessages[] = $msg;
            }
        }
        return new self(...$newMessages);
    }

    // ACCESS METHODS ////////////////////////////////////

    public function getMessage(string $id): ?Message {
        foreach ($this->messages as $msg) {
            if ($msg->id === $id) {
                return $msg;
            }
        }
        return null;
    }

    public function hasMessage(string $id): bool {
        return $this->getMessage($id) !== null;
    }

    public function first(): ?Message {
        return $this->messages[0] ?? null;
    }

    public function last(): ?Message {
        return $this->messages[count($this->messages) - 1] ?? null;
    }

    public function count(): int {
        return count($this->messages);
    }

    public function isEmpty(): bool {
        return empty($this->messages);
    }

    public function getByRole(MessageRole $role): self {
        $filtered = array_filter(
            $this->messages,
            fn($msg) => $msg->role === $role
        );
        return new self(...$filtered);
    }

    public function excludeRole(MessageRole $role): self {
        $filtered = array_filter(
            $this->messages,
            fn($msg) => $msg->role !== $role
        );
        return new self(...$filtered);
    }

    public function since(string $messageId): self {
        $newMessages = [];
        $found = false;
        foreach ($this->messages as $msg) {
            if ($msg->id === $messageId) {
                $found = true;
            }
            if ($found) {
                $newMessages[] = $msg;
            }
        }
        return new self(...$newMessages);
    }

    public function until(string $messageId): self {
        $newMessages = [];
        foreach ($this->messages as $msg) {
            $newMessages[] = $msg;
            if ($msg->id === $messageId) {
                break;
            }
        }
        return new self(...$newMessages);
    }

    // ITERATORS ////////////////////////////////////////

    public function map(callable $callback): self {
        return new self(...array_map($callback, $this->messages));
    }

    public function filter(callable $callback): self {
        return new self(...array_filter($this->messages, $callback));
    }

    public function reduce(callable $callback, mixed $initial = null): mixed {
        return array_reduce($this->messages, $callback, $initial);
    }

    public function mapByContentParts(callable $callback): self {
        $newMessages = [];
        foreach ($this->messages as $msg) {
            $parts = $msg->content->getParts();
            $newParts = array_map($callback, $parts);
            $newMessages[] = $msg->withContent(Content::parts(...$newParts));
        }
        return new self(...$newMessages);
    }

    public function filterByContentParts(callable $callback): self {
        $newMessages = [];
        foreach ($this->messages as $msg) {
            $parts = array_filter($msg->content->getParts(), $callback);
            if (!empty($parts)) {
                $newMessages[] = $msg->withContent(Content::parts(...$parts));
            }
        }
        return new self(...$newMessages);
    }

    public function reduceByContentParts(callable $callback, mixed $initial = null): mixed {
        $allParts = [];
        foreach ($this->messages as $msg) {
            $allParts = [...$allParts, ...$msg->content->getParts()];
        }
        return array_reduce($allParts, $callback, $initial);
    }

    // CONVERSION METHODS ///////////////////////////////

    public function toArray(): array {
        return array_map(fn($msg) => $msg->toArray(), $this->messages);
    }

    public function __toString(): string {
        return implode("\n", array_map(fn($msg) => $msg->__toString(), $this->messages));
    }

    public function __invoke(Message $message): self {
        return $this->add($message);
    }

    public function getIterator(): Iterator {
        return new ArrayIterator($this->messages);
    }
}
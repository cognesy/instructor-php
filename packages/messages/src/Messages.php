<?php

declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Support\MessagesInput;
use Countable;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use RuntimeException;
use Traversable;

/** @implements IteratorAggregate<int, Message> */
final readonly class Messages implements Countable, IteratorAggregate
{
    private MessageList $messages;

    public function __construct(Message ...$messages)
    {
        $this->messages = new MessageList(...$messages);
    }

    #[\Override]
    public function getIterator(): Traversable
    {
        return $this->messages->getIterator();
    }

    // CONSTRUCTORS ///////////////////////////////////////////////////////////
    public static function empty(): static
    {
        return new self;
    }

    public static function fromString(string $content, string $role = 'user'): Messages
    {
        return new Messages(Message::fromString($content, $role));
    }

    /**
     * @param  list<string|array<array-key, mixed>>  $messages  List of messages (strings or arrays with role/content)
     */
    public static function fromArray(array $messages): Messages
    {
        return MessagesInput::fromArray($messages);
    }

    public static function fromList(MessageList $messages): Messages
    {
        return new Messages(...$messages->all());
    }

    /** @param array|Message[]|Messages $arrayOfMessages */
    public static function fromMessages(array|Messages $arrayOfMessages): Messages
    {
        if ($arrayOfMessages instanceof Messages) {
            return $arrayOfMessages;
        }
        $newMessages = [];
        foreach ($arrayOfMessages as $message) {
            $newMessages[] = match (true) {
                $message instanceof Message => $message,
                is_array($message) && Message::isMessage($message) => Message::fromArray($message),
                default => throw new InvalidArgumentException('Invalid type for message'),
            };
        }

        return new Messages(...$newMessages);
    }

    public static function fromAnyArray(array $messages): Messages
    {
        return MessagesInput::fromAnyArray($messages);
    }

    public static function fromAny(string|array|Message|Messages|MessageList $messages): Messages
    {
        return MessagesInput::fromAny($messages);
    }

    public static function fromInput(string|array|object $input): static
    {
        return MessagesInput::fromInput($input);
    }

    // MUTATORS /////////////////////////////////////////////////////////////

    public function asSystem(string|array|Message|Messages|Content|ContentPart|ContentParts $message, string $name = ''): static
    {
        return $this->appendWithRole(MessageRole::System, $message, $name);
    }

    public function asDeveloper(string|array|Message|Messages|Content|ContentPart|ContentParts $message, string $name = ''): static
    {
        return $this->appendWithRole(MessageRole::Developer, $message, $name);
    }

    public function asUser(string|array|Message|Messages|Content|ContentPart|ContentParts $message, string $name = ''): static
    {
        return $this->appendWithRole(MessageRole::User, $message, $name);
    }

    public function asAssistant(string|array|Message|Messages|Content|ContentPart|ContentParts $message, string $name = ''): static
    {
        return $this->appendWithRole(MessageRole::Assistant, $message, $name);
    }

    public function asTool(string|array|Message|Messages|Content|ContentPart|ContentParts $message, string $name = ''): static
    {
        return $this->appendWithRole(MessageRole::Tool, $message, $name);
    }

    public function withMessages(array|Messages|MessageList $messages): static
    {
        $list = match (true) {
            $messages instanceof Messages => $messages->list(),
            $messages instanceof MessageList => $messages,
            default => Messages::fromAnyArray($messages)->list(),
        };

        return new static(...$list->all());
    }

    public function appendMessage(string|array|Message $message): static
    {
        $message = match (true) {
            is_string($message) => Message::fromString($message),
            is_array($message) => Message::fromArray($message),
            default => $message,
        };

        // Constructed straight from the MessageList. Routing this through withMessages()
        // sent the whole accumulated list back through MessagesInput::fromAnyArray() on
        // every append, so appending N messages walked the list N times.
        return new static(...$this->messages->add($message)->all());
    }

    public function appendMessages(array|Messages|MessageList $messages): static
    {
        if (Messages::becomesEmpty($messages)) {
            return $this;
        }
        $appended = match (true) {
            $messages instanceof Messages => $messages->list(),
            $messages instanceof MessageList => $messages,
            default => Messages::fromAnyArray($messages)->list(),
        };

        return new static(...$this->messages->addAll($appended)->all());
    }

    public function prependMessages(array|Messages|Message|MessageList $messages): static
    {
        $list = match (true) {
            empty($messages) => $this->messages,
            $messages instanceof Message => MessageList::fromArray([$messages]),
            $messages instanceof Messages => $messages->list(),
            $messages instanceof MessageList => $messages,
            default => Messages::fromAnyArray($messages)->list(),
        };

        return new static(...$this->messages->prependAll($list)->all());
    }

    public function removeHead(): static
    {
        return new static(...$this->messages->removeHead()->all());
    }

    public function removeTail(): static
    {
        return new static(...$this->messages->removeTail()->all());
    }

    public function appendContentField(string $key, mixed $value): static
    {
        $lastMessage = $this->messages->last() ?? Message::empty();
        $newContent = $lastMessage->content()->appendContentField($key, $value);
        $messages = $this->messages->replaceLast($lastMessage->withContent($newContent));

        return new static(...$messages->all());
    }

    // CONVERSION / TRANSFORMATION /////////////////////////////////////////

    /**
     * Renders an array of message arrays to a single string.
     *
     * $separator applies to the DEFAULT rendering only. A custom $renderer owns its own
     * separation and receives no separator - it is handed one message at a time and its
     * return value is concatenated verbatim, so a renderer that wants messages on
     * separate lines must emit the newline itself. This is deliberate: a renderer
     * producing its own framing (JSON lines, XML tags, a chat transcript) would
     * otherwise get an extra separator injected into markup it controls.
     *
     * Messages with empty content are skipped, and composite (multi-part) messages throw
     * unless a $renderer is supplied to handle them.
     *
     * @param  callable(array): string|null  $renderer  owns its own separation; $separator is ignored for it
     */
    public static function asString(
        array $messages,
        string $separator = "\n",
        ?callable $renderer = null
    ): string {
        $result = '';
        foreach ($messages as $message) {
            if (empty($message) || ! is_array($message) || empty($message['content'])) {
                continue;
            }
            $rendered = match (true) {
                $renderer !== null => $renderer($message),
                default => match (true) {
                    Message::becomesComposite($message) => throw new RuntimeException('Array contains composite messages, cannot be converted to string.'),
                    default => Message::fromAny($message)->toString().$separator,
                }
            };
            $result .= $rendered;
        }

        return $result;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function toArray(): array
    {
        return $this->messages->toArray();
    }

    public function toString(string $separator = "\n"): string
    {
        if ($this->hasComposites()) {
            throw new RuntimeException('Collection contains composite messages and cannot be converted to string.');
        }

        return self::asString($this->toArray(), $separator);
    }

    /**
     * Collapses each run of consecutive same-role messages into one message.
     *
     * The merged message is the FIRST message of the run, extended with the content and
     * tool calls of the rest - it is not a fresh Message. That keeps the run's id,
     * parentId, createdAt, name and metadata, so a merged conversation stays anchored in
     * a stored parentId chain. Previously this built `new Message($role)` and applied
     * only content, silently discarding tool calls, tool results, metadata, name and
     * identity; for an assistant turn that dropped exactly the tool_calls the model needs
     * to continue the tool loop.
     *
     * A message carrying a tool result is never merged - it is emitted on its own and
     * breaks the run, because a tool result is bound to a single tool call id.
     *
     * @see Message::withMergedFrom()
     */
    public function toMergedPerRole(): Messages
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $merged = MessageList::empty();
        $pending = null;
        foreach ($this->messages->all() as $message) {
            $startsNewRun = $pending === null
                || $message->role()->isNot($pending->role())
                || $pending->hasToolResult()
                || $message->hasToolResult();
            if ($startsNewRun) {
                $merged = match (true) {
                    $pending === null => $merged,
                    default => $merged->add($pending),
                };
                $pending = $message;

                continue;
            }
            $pending = $pending->withMergedFrom($message);
        }

        return Messages::fromList(match (true) {
            $pending === null => $merged,
            default => $merged->add($pending),
        });
    }

    /** @param string[]|MessageRole[] $roles */
    public function forRoles(array $roles): Messages
    {
        $roleEnums = MessageRole::normalizeArray($roles);
        $selected = [];
        foreach ($this->messages->all() as $message) {
            if ($message->role()->oneOf(...$roleEnums)) {
                $selected[] = $message;
            }
        }

        return new Messages(...$selected);
    }

    /** @param string[]|MessageRole[] $roles */
    public function exceptRoles(array $roles): Messages
    {
        $roleEnums = MessageRole::normalizeArray($roles);
        $selected = [];
        foreach ($this->messages->all() as $message) {
            if (! $message->role()->oneOf(...$roleEnums)) {
                $selected[] = $message;
            }
        }

        return new Messages(...$selected);
    }

    /** @param string[]|MessageRole[] $roles */
    public function headWithRoles(array $roles): Messages
    {
        $roleEnums = MessageRole::normalizeArray($roles);
        $head = [];
        foreach ($this->messages->all() as $message) {
            if (! $message->role()->oneOf(...$roleEnums)) {
                break;
            }
            $head[] = $message;
        }

        return new Messages(...$head);
    }

    /** @param string[]|MessageRole[] $roles */
    public function tailAfterRoles(array $roles): Messages
    {
        $inHead = true;
        $roleEnums = MessageRole::normalizeArray($roles);
        $tail = [];
        foreach ($this->messages->all() as $message) {
            if ($inHead && $message->role()->oneOf(...$roleEnums)) {
                continue;
            }
            if ($inHead && ! $message->role()->oneOf(...$roleEnums)) {
                $inHead = false;
            }
            $tail[] = $message;
        }

        return new Messages(...$tail);
    }

    /** @param array<string, string|MessageRole> $mapping */
    public function remapRoles(array $mapping): Messages
    {
        $remapped = [];
        foreach ($this->messages->all() as $message) {
            $role = $message->role()->value;
            $remapped[] = $message->withRole($mapping[$role] ?? $role);
        }

        return new Messages(...$remapped);
    }

    public function contentParts(): ContentParts
    {
        $parts = [];
        foreach ($this->messages->all() as $message) {
            foreach ($message->contentParts()->all() as $part) {
                $parts[] = $part;
            }
        }

        return ContentParts::fromArray($parts);
    }

    public function reversed(): Messages
    {
        return new Messages(...$this->messages->reversed()->all());
    }

    public function withoutEmptyMessages(): Messages
    {
        return new Messages(...$this->messages->withoutEmpty()->all());
    }

    // ACCESSORS ///////////////////////////////////////////////////////////

    public function first(): Message
    {
        if ($this->messages->isEmpty()) {
            return new Message;
        }

        return $this->messages->first() ?? new Message;
    }

    public function last(): Message
    {
        if ($this->messages->isEmpty()) {
            return new Message;
        }

        return $this->messages->last() ?? new Message;
    }

    public function getById(MessageId $id): ?Message
    {
        foreach ($this->messages->all() as $message) {
            if ($message->id()->equals($id)) {
                return $message;
            }
        }

        return null;
    }

    public function hasId(MessageId $id): bool
    {
        return $this->getById($id) !== null;
    }

    /**
     * @return Generator<Message>
     */
    public function each(): iterable
    {
        foreach ($this->messages->all() as $message) {
            yield $message;
        }
    }

    public function hasComposites(): bool
    {
        return $this->reduce(fn (bool $carry, Message $message) => $carry || $message->isComposite(), false);
    }

    /** @deprecated Use headList() for collection access. */
    public function head(): array
    {
        if ($this->messages->isEmpty()) {
            return [];
        }

        return array_slice($this->messages->all(), 0, 1);
    }

    /** @deprecated Use tailList() for collection access. */
    public function tail(): array
    {
        if ($this->messages->isEmpty()) {
            return [];
        }

        return array_slice($this->messages->all(), $this->messages->count() - 1);
    }

    public function headList(): MessageList
    {
        return MessageList::fromArray($this->head());
    }

    public function tailList(): MessageList
    {
        return MessageList::fromArray($this->tail());
    }

    public static function becomesEmpty(array|Message|Messages|MessageList $messages): bool
    {
        return match (true) {
            is_array($messages) && empty($messages) => true,
            $messages instanceof Message => $messages->isEmpty(),
            $messages instanceof Messages => $messages->isEmpty(),
            $messages instanceof MessageList => $messages->isEmpty(),
            default => false,
        };
    }

    public static function becomesComposite(array $messages): bool
    {
        return match (true) {
            empty($messages) => false,
            default => Messages::fromMessages($messages)->hasComposites(),
        };
    }

    public function isEmpty(): bool
    {
        return match (true) {
            $this->messages->isEmpty() => true,
            default => $this->reduce(
                fn (bool $carry, Message $message) => $carry && $message->isEmpty(),
                true,
            ),
        };
    }

    public function notEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * @template T
     *
     * @param  callable(T, Message): T  $callback
     * @param  T  $initial
     * @return T
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return $this->messages->reduce($callback, $initial);
    }

    /**
     * @template T
     *
     * @param  callable(Message): T  $callback
     * @return array<T>
     */
    public function map(callable $callback): array
    {
        return $this->messages->map($callback);
    }

    /**
     * Keeps the messages the predicate accepts - nothing else. Empty messages are NOT
     * dropped: silently discarding them made filter() unable to select them, so
     * `filter(fn($m) => $m->isEmpty())` returned nothing at all. Use
     * withoutEmptyMessages() when you want that removal, on its own or composed.
     *
     * @param  callable(Message): bool|null  $callback  null keeps every message; deprecated, see below
     */
    public function filter(?callable $callback = null): Messages
    {
        if ($callback === null) {
            // @deprecated Calling filter() with no predicate used to mean "drop empty
            // messages". Call withoutEmptyMessages() for that; this alias preserves the
            // old meaning for existing callers and will be removed.
            return $this->withoutEmptyMessages();
        }

        $filteredMessages = [];
        foreach ($this->messages->all() as $message) {
            if ($callback($message)) {
                $filteredMessages[] = $message;
            }
        }

        return new Messages(...$filteredMessages);
    }

    #[\Override]
    public function count(): int
    {
        return $this->messages->count();
    }

    public function firstRole(): MessageRole
    {
        if ($this->isEmpty()) {
            throw new \RuntimeException('Cannot get role of first message - no messages available');
        }

        return $this->first()->role();
    }

    public function lastRole(): MessageRole
    {
        if ($this->isEmpty()) {
            throw new \RuntimeException('Cannot get role of last message - no messages available');
        }

        return $this->last()->role();
    }

    /**  @return Message[] @deprecated Use messageList() for collection access. */
    public function all(): array
    {
        return $this->messages->all();
    }

    public function messageList(): MessageList
    {
        return $this->messages;
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function list(): MessageList
    {
        return $this->messages;
    }

    private function appendWithRole(
        MessageRole $role,
        string|array|Message|Messages|Content|ContentPart|ContentParts $message,
        string $name = ''
    ): static {
        return match (true) {
            $message instanceof Messages => $this->appendMessages($message),
            default => $this->appendMessage(Message::fromAny($message, $role, $name)),
        };
    }
}

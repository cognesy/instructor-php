<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Support\MessagesInput;
use Cognesy\Messages\ContentParts;
use Countable;
use IteratorAggregate;
use Traversable;
use Generator;
use InvalidArgumentException;
use RuntimeException;

final readonly class Messages implements Countable, IteratorAggregate
{
    /** @var MessageList $messages */
    private MessageList $messages;

    public function __construct(Message ...$messages) {
        $this->messages = new MessageList(...$messages);
    }

    public function getIterator(): Traversable {
        return $this->messages->getIterator();
    }

    // CONSTRUCTORS ///////////////////////////////////////////////////////////
    public static function empty(): static {
        return new static();
    }

    static public function fromString(string $content, string $role = 'user') : Messages {
        return new Messages(Message::fromString($content, $role));
    }

    /**
     * @param list<string|array<array-key, mixed>> $messages List of messages (strings or arrays with role/content)
     */
    static public function fromArray(array $messages) : Messages {
        return MessagesInput::fromArray($messages);
    }

    public static function fromList(MessageList $messages) : Messages {
        return new Messages(...$messages->all());
    }

    /** @param array|Message[]|Messages $arrayOfMessages */
    static public function fromMessages(array|Messages $arrayOfMessages) : Messages {
        if ($arrayOfMessages instanceof Messages) {
            return $arrayOfMessages;
        }
        $newMessages = [];
        foreach ($arrayOfMessages as $message) {
            $newMessages[] = match(true) {
                $message instanceof Message => $message,
                is_array($message) && Message::isMessage($message) => Message::fromArray($message),
                default => throw new InvalidArgumentException('Invalid type for message'),
            };
        }
        return new Messages(...$newMessages);
    }

    public static function fromAnyArray(array $messages) : Messages {
        return MessagesInput::fromAnyArray($messages);
    }

    public static function fromAny(string|array|Message|Messages|MessageList $messages) : Messages {
        return MessagesInput::fromAny($messages);
    }

    public static function fromInput(string|array|object $input) : static {
        return MessagesInput::fromInput($input);
    }

    public function clone() : self {
        return new Messages(...$this->messages->map(fn(Message $message) => $message->clone()));
    }

    // MUTATORS /////////////////////////////////////////////////////////////

    public function asSystem(string|array|Message|Messages|Content|ContentPart|ContentParts $message, string $name = '') : static {
        return $this->appendWithRole(MessageRole::System, $message, $name);
    }

    public function asDeveloper(string|array|Message|Messages|Content|ContentPart|ContentParts $message, string $name = '') : static {
        return $this->appendWithRole(MessageRole::Developer, $message, $name);
    }

    public function asUser(string|array|Message|Messages|Content|ContentPart|ContentParts $message, string $name = '') : static {
        return $this->appendWithRole(MessageRole::User, $message, $name);
    }

    public function asAssistant(string|array|Message|Messages|Content|ContentPart|ContentParts $message, string $name = '') : static {
        return $this->appendWithRole(MessageRole::Assistant, $message, $name);
    }

    public function asTool(string|array|Message|Messages|Content|ContentPart|ContentParts $message, string $name = '') : static {
        return $this->appendWithRole(MessageRole::Tool, $message, $name);
    }

    public function withMessage(string|array|Message $message) : static {
        $newMessages = match (true) {
            is_string($message) => [Message::fromString($message)],
            is_array($message) => [Message::fromArray($message)],
            default => [$message],
        };
        return new static(...$newMessages);
    }

    public function withMessages(array|Messages|MessageList $messages) : static {
        $list = match (true) {
            $messages instanceof Messages => $messages->list(),
            $messages instanceof MessageList => $messages,
            default => Messages::fromAnyArray($messages)->list(),
        };
        return new static(...$list->all());
    }

    public function appendMessage(string|array|Message $message) : static {
        $message = match (true) {
            is_string($message) => Message::fromString($message),
            is_array($message) => Message::fromArray($message),
            default => $message,
        };
        return $this->withMessages($this->messages->add($message)->all());
    }

    public function appendMessages(array|Messages|MessageList $messages) : static {
        if (Messages::becomesEmpty($messages)) {
            return $this;
        }
        $appended = match (true) {
            $messages instanceof Messages => $messages->list(),
            $messages instanceof MessageList => $messages,
            default => Messages::fromAnyArray($messages)->list(),
        };
        return $this->withMessages($this->messages->addAll($appended)->all());
    }

    public function prependMessages(array|Messages|Message|MessageList $messages) : static {
        $list = match (true) {
            empty($messages) => $this->messages,
            $messages instanceof Message => MessageList::fromArray([$messages]),
            $messages instanceof Messages => $messages->list(),
            $messages instanceof MessageList => $messages,
            default => Messages::fromAnyArray($messages)->list(),
        };
        return $this->withMessages($this->messages->prependAll($list)->all());
    }

    public function removeHead() : static {
        return $this->withMessages($this->messages->removeHead()->all());
    }

    public function removeTail() : static {
        return $this->withMessages($this->messages->removeTail()->all());
    }

    public function appendContentField(string $key, mixed $value) : static {
        $lastMessage = $this->messages->last() ?? Message::empty();
        $newContent = $lastMessage->content()->appendContentField($key, $value);
        $messages = $this->messages->replaceLast($lastMessage->withContent($newContent));
        return new static(...$messages->all());
    }

    // CONVERSION / TRANSFORMATION /////////////////////////////////////////

    /**
     * @param callable(array): string|null $renderer
     */
    public static function asString(
        array $messages,
        string $separator = "\n",
        ?callable $renderer = null
    ) : string {
        $result = '';
        foreach ($messages as $message) {
            if (empty($message) || !is_array($message) || empty($message['content'])) {
                continue;
            }
            $rendered = match(true) {
                $renderer !== null => $renderer($message),
                default => match(true) {
                    Message::becomesComposite($message) => throw new RuntimeException('Array contains composite messages, cannot be converted to string.'),
                    default => Message::fromAny($message)->toString() . $separator,
                }
            };
            $result .= $rendered;
        }
        return $result;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function toArray() : array {
        return $this->messages
            ->withoutEmpty()
            ->toArray();
    }

    public function toString(string $separator = "\n") : string {
        if ($this->hasComposites()) {
            throw new RuntimeException('Collection contains composite messages and cannot be converted to string.');
        }
        return self::asString($this->toArray(), $separator);
    }

    public function toMergedPerRole(): Messages {
        if ($this->isEmpty()) {
            return $this;
        }
        $messages = Messages::empty();
        $role = $this->firstRole();
        $newMessage = new Message($role);
        foreach ($this->messages->all() as $message) {
            if ($message->role()->isNot($role)) {
                $messages = $messages->appendMessage($newMessage);
                $role = $message->role();
                $newMessage = new Message($role);
            }
            $newMessage = $newMessage->addContentFrom($message);
        }
        $messages = $messages->appendMessage($newMessage);
        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function forRoles(array $roles): Messages {
        $messages = Messages::empty();
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages->all() as $message) {
            if ($message->role()->oneOf(...$roleEnums)) {
                $messages = $messages->appendMessage($message);
            }
        }
        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function exceptRoles(array $roles): Messages {
        $messages = Messages::empty();
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages->all() as $message) {
            if (!$message->role()->oneOf(...$roleEnums)) {
                $messages = $messages->appendMessage($message);
            }
        }
        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function headWithRoles(array $roles): Messages {
        $messages = Messages::empty();
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages->all() as $message) {
            if (!$message->role()->oneOf(...$roleEnums)) {
                break;
            }
            $messages = $messages->appendMessage($message);
        }
        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function tailAfterRoles(array $roles): Messages {
        $messages = Messages::empty();
        $inHead = true;
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages->all() as $message) {
            if ($inHead && $message->role()->oneOf(...$roleEnums)) {
                continue;
            }
            if ($inHead && !$message->role()->oneOf(...$roleEnums)) {
                $inHead = false;
            }
            $messages = $messages->appendMessage($message);
        }
        return $messages;
    }

    /** @param array<string, string|MessageRole> $mapping */
    public function remapRoles(array $mapping): Messages {
        $messages = Messages::empty();
        foreach ($this->messages->all() as $message) {
            $role = $message->role()->value;
            $messages = $messages->appendMessage($message->withRole($mapping[$role] ?? $role));
        }
        return $messages;
    }

    public function contentParts(): ContentParts {
        $parts = [];
        foreach ($this->messages->all() as $message) {
            foreach ($message->contentParts()->all() as $part) {
                $parts[] = $part;
            }
        }
        return ContentParts::fromArray($parts);
    }

    public function reversed(): Messages {
        return new Messages(...$this->messages->reversed()->all());
    }

    public function withoutEmptyMessages(): Messages {
        return new Messages(...$this->messages->withoutEmpty()->all());
    }

    // ACCESSORS ///////////////////////////////////////////////////////////

    public function first() : Message {
        if ($this->messages->isEmpty()) {
            return new Message();
        }
        return $this->messages->first() ?? new Message();
    }

    public function last() : Message {
        if ($this->messages->isEmpty()) {
            return new Message();
        }
        return $this->messages->last() ?? new Message();
    }

    /**
     * @return Generator<Message>
     */
    public function each() : iterable {
        foreach ($this->messages->all() as $message) {
            yield $message;
        }
    }

    public function hasComposites() : bool {
        return $this->reduce(fn(bool $carry, Message $message) => $carry || $message->isComposite(), false);
    }

    /** @deprecated Use headList() for collection access. */
    public function head() : array {
        if ($this->messages->isEmpty()) {
            return [];
        }
        return array_slice($this->messages->all(), 0, 1);
    }

    /** @deprecated Use tailList() for collection access. */
    public function tail() : array {
        if ($this->messages->isEmpty()) {
            return [];
        }
        return array_slice($this->messages->all(), $this->messages->count() - 1);
    }

    public function headList(): MessageList {
        return MessageList::fromArray($this->head());
    }

    public function tailList(): MessageList {
        return MessageList::fromArray($this->tail());
    }

    public static function becomesEmpty(array|Message|Messages|MessageList $messages) : bool {
        return match(true) {
            is_array($messages) && empty($messages) => true,
            $messages instanceof Message => $messages->isEmpty(),
            $messages instanceof Messages => $messages->isEmpty(),
            $messages instanceof MessageList => $messages->isEmpty(),
            default => false,
        };
    }

    public static function becomesComposite(array $messages) : bool {
        return match(true) {
            empty($messages) => false,
            default => Messages::fromMessages($messages)->hasComposites(),
        };
    }

    public function isEmpty() : bool {
        return match(true) {
            $this->messages->isEmpty() => true,
            default => $this->reduce(
                fn(bool $carry, Message $message) => $carry && $message->isEmpty(),
                true,
            ),
        };
    }

    public function notEmpty() : bool {
        return !$this->isEmpty();
    }

    /**
     * @template T
     * @param callable(T, Message): T $callback
     * @param T $initial
     * @return T
     */
    public function reduce(callable $callback, mixed $initial = null) : mixed {
        return $this->messages->reduce($callback, $initial);
    }

    /**
     * @template T
     * @param callable(Message): T $callback
     * @return array<T>
     */
    public function map(callable $callback) : array {
        return $this->messages->map($callback);
    }

    /**
     * @param callable(Message): bool|null $callback
     */
    public function filter(?callable $callback = null) : Messages {
        $filteredMessages = [];
        foreach ($this->messages->all() as $message) {
            if ($message->isEmpty()) {
                continue;
            }
            if ($callback === null) {
                $filteredMessages[] = $message->clone();
                continue;
            }
            if ($callback($message)) {
                $filteredMessages[] = $message->clone();
            }
        }
        return new Messages(...$filteredMessages);
    }

    public function count() : int {
        return $this->messages->count();
    }

    public function firstRole() : MessageRole {
        $first = $this->first();
        if (!($first instanceof Message)) {
            throw new \RuntimeException('Cannot get role of first message - no messages available');
        }
        return $first->role();
    }

    public function lastRole() : MessageRole {
        $last = $this->last();
        if (!($last instanceof Message)) {
            throw new \RuntimeException('Cannot get role of last message - no messages available');
        }
        return $last->role();
    }

    /**  @return Message[] @deprecated Use messageList() for collection access. */
    public function all() : array {
        return $this->messages->all();
    }

    public function messageList(): MessageList {
        return $this->messages;
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function list(): MessageList {
        return $this->messages;
    }

    private function appendWithRole(
        MessageRole $role,
        string|array|Message|Messages|Content|ContentPart|ContentParts $message,
        string $name = ''
    ) : static {
        return match(true) {
            $message instanceof Messages => $this->appendMessages($message),
            default => $this->appendMessage(Message::fromAny($message, $role, $name)),
        };
    }
}

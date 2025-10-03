<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Messages\Contracts\CanProvideMessage;
use Cognesy\Messages\Contracts\CanProvideMessages;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Utils\TextRepresentation;
use Generator;
use InvalidArgumentException;
use RuntimeException;

final readonly class Messages
{
    /** @var Message[] $messages */
    private array $messages;

    public function __construct(Message ...$messages) {
        $this->messages = $messages;
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
        $newMessages = [];
        foreach ($messages as $message) {
            $newMessages[] = match(true) {
                is_string($message) => Message::fromString($message),
                Message::hasRoleAndContent($message) => Message::fromArray($message),
                default => throw new InvalidArgumentException('Invalid message array - missing role or content keys'),
            };
        }
        return new Messages(...$newMessages);
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
                is_array($message) && Message::hasRoleAndContent($message) => Message::fromArray($message),
                default => throw new InvalidArgumentException('Invalid type for message'),
            };
        }
        return new Messages(...$newMessages);
    }

    public static function fromAnyArray(array $messages) : Messages {
        if (Message::hasRoleAndContent($messages)) {
            return self::fromArray([$messages]);
        }
        $newMessages = [];
        foreach ($messages as $message) {
            $newMessages[] = match(true) {
                is_array($message) => match(true) {
                    Message::hasRoleAndContent($message) => Message::fromArray($message),
                    default => throw new InvalidArgumentException('Invalid message array - missing role or content keys'),
                },
                is_string($message) => new Message('user', $message),
                $message instanceof Message => $message,
                default => throw new InvalidArgumentException('Invalid message type'),
            };
        }
        return new Messages(...$newMessages);
    }

    public static function fromAny(string|array|Message|Messages $messages) : Messages {
        return match(true) {
            is_string($messages) => self::fromString($messages),
            is_array($messages) => self::fromAnyArray($messages),
            $messages instanceof Message => new Messages($messages),
            $messages instanceof Messages => $messages,
            default => throw new InvalidArgumentException('Invalid message type'),
        };
    }

    public static function fromInput(string|array|object $input) : static {
        return match(true) {
            $input instanceof Messages => $input,
            $input instanceof CanProvideMessages => $input->toMessages(),
            $input instanceof Message => new Messages($input),
            $input instanceof CanProvideMessage => new Messages($input->toMessage()),
            default => new Messages(new Message(role: 'user', content: TextRepresentation::fromAny($input))),
        };
    }

    public function clone() : self {
        return new Messages(...array_map(fn($message) => $message->clone(), $this->messages));
    }

    // MUTATORS /////////////////////////////////////////////////////////////

    public function asSystem(string|array|Message|Messages|Content|ContentPart $message, string $name = '') : static {
        return match(true) {
            $message instanceof Messages => $this->appendMessages($message),
            default => $this->appendMessage(Message::fromAny($message, MessageRole::System, $name)),
        };
    }

    public function asDeveloper(string|array|Message|Messages|Content|ContentPart $message, string $name = '') : static {
        return match(true) {
            $message instanceof Messages => $this->appendMessages($message),
            default => $this->appendMessage(Message::fromAny($message, MessageRole::Developer, $name)),
        };
    }

    public function asUser(string|array|Message|Messages|Content|ContentPart $message, string $name = '') : static {
        return match(true) {
            $message instanceof Messages => $this->appendMessages($message),
            default => $this->appendMessage(Message::fromAny($message, MessageRole::User, $name)),
        };
    }

    public function asAssistant(string|array|Message|Messages|Content|ContentPart $message, string $name = '') : static {
        return match(true) {
            $message instanceof Messages => $this->appendMessages($message),
            default => $this->appendMessage(Message::fromAny($message, MessageRole::Assistant, $name)),
        };
    }

    public function asTool(string|array|Message|Messages|Content|ContentPart $message, string $name = '') : static {
        return match(true) {
            $message instanceof Messages => $this->appendMessages($message),
            default => $this->appendMessage(Message::fromAny($message, MessageRole::Tool, $name)),
        };
    }

    public function withMessage(string|array|Message $message) : static {
        $newMessages = match (true) {
            is_string($message) => [Message::fromString($message)],
            is_array($message) => [Message::fromArray($message)],
            default => [$message],
        };
        return new static(...$newMessages);
    }

    public function withMessages(array|Messages $messages) : static {
        $newMessages = match (true) {
            $messages instanceof Messages => $messages->messages,
            default => Messages::fromAnyArray($messages)->messages,
        };
        return new static(...$newMessages);
    }

    public function appendMessage(string|array|Message $message) : static {
        $newMessages = $this->messages;
        $newMessages[] = match (true) {
            is_string($message) => Message::fromString($message),
            is_array($message) => Message::fromArray($message),
            default => $message,
        };
        return $this->withMessages($newMessages);
    }

    public function appendMessages(array|Messages $messages) : static {
        if (Messages::becomesEmpty($messages)) {
            return $this;
        }
        $appended = match (true) {
            $messages instanceof Messages => $messages->messages,
            default => Messages::fromAnyArray($messages)->messages,
        };
        $newMessages = array_merge($this->messages, $appended);
        return $this->withMessages($newMessages);
    }

    public function prependMessages(array|Messages|Message $messages) : static {
        $newMessages = match (true) {
            empty($messages) => $this->messages,
            $messages instanceof Message => array_merge([$messages], $this->messages),
            $messages instanceof Messages => array_merge($messages->messages, $this->messages),
            default => array_merge(Messages::fromAnyArray($messages)->messages, $this->messages),
        };
        return $this->withMessages($newMessages);
    }

    public function removeHead() : static {
        $newMessages = $this->messages;
        array_shift($newMessages);
        return $this->withMessages($newMessages);
    }

    public function removeTail() : static {
        $newMessages = $this->messages;
        array_pop($newMessages);
        return $this->withMessages($newMessages);
    }

    public function appendContentField(string $key, mixed $value) : static {
        $newMessages = $this->messages;
        $lastMessage = $newMessages[array_key_last($newMessages)] ?? Message::empty();
        $newContent = $lastMessage->content()->appendContentField($key, $value);
        $messagesExceptLast = array_slice($newMessages, 0, -1);
        $messages = [...$messagesExceptLast, $lastMessage->withContent($newContent)];
        return new static(...$messages);
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
        $result = [];
        foreach ($this->messages as $message) {
            if ($message->isEmpty()) {
                continue;
            }
            $result[] = $message->toArray();
        }
        return $result;
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
        foreach ($this->all() as $message) {
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
        foreach ($this->messages as $message) {
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
        foreach ($this->messages as $message) {
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
        foreach ($this->messages as $message) {
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
        foreach ($this->messages as $message) {
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
        foreach ($this->messages as $message) {
            $role = $message->role()->value;
            $messages = $messages->appendMessage($message->withRole($mapping[$role] ?? $role));
        }
        return $messages;
    }

    /** @return ContentPart[] */
    public function contentParts(): array {
        $parts = [];
        foreach ($this->messages as $message) {
            foreach ($message->contentParts() as $part) {
                $parts[] = $part;
            }
        }
        return $parts;
    }

    public function reversed(): Messages {
        return new Messages(...array_reverse($this->messages));
    }

    public function withoutEmptyMessages(): Messages {
        $messages = Messages::empty();
        foreach ($this->messages as $message) {
            if (!$message->isEmpty()) {
                $messages = $messages->appendMessage($message);
            }
        }
        return $messages;
    }

    // ACCESSORS ///////////////////////////////////////////////////////////

    public function first() : Message {
        if (empty($this->messages)) {
            return new Message();
        }
        return $this->messages[0];
    }

    public function last() : Message {
        if (empty($this->messages)) {
            return new Message();
        }
        return $this->messages[count($this->messages)-1];
    }

    /**
     * @return Generator<Message>
     */
    public function each() : iterable {
        foreach ($this->messages as $message) {
            yield $message;
        }
    }

    public function hasComposites() : bool {
        return $this->reduce(fn(bool $carry, Message $message) => $carry || $message->isComposite(), false);
    }

    public function head() : array {
        if (empty($this->messages)) {
            return [];
        }
        return array_slice($this->messages, 0, 1);
    }

    public function tail() : array {
        if (empty($this->messages)) {
            return [];
        }
        return array_slice($this->messages, count($this->messages)-1);
    }

    public static function becomesEmpty(array|Message|Messages $messages) : bool {
        return match(true) {
            is_array($messages) && empty($messages) => true,
            $messages instanceof Message => $messages->isEmpty(),
            $messages instanceof Messages => $messages->isEmpty(),
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
            empty($this->messages) => true,
            default => $this->reduce(fn(bool $carry, Message $message) => $carry && $message->isEmpty(), true),
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
        return array_reduce($this->messages, $callback, $initial);
    }

    /**
     * @template T
     * @param callable(Message): T $callback
     * @return array<T>
     */
    public function map(callable $callback) : array {
        return array_map($callback, $this->messages);
    }

    /**
     * @param callable(Message): bool|null $callback
     */
    public function filter(?callable $callback = null) : Messages {
        $filteredMessages = [];
        foreach ($this->messages as $message) {
            if ($message->isEmpty()) {
                continue;
            }
            if ($callback !== null && $callback($message)) {
                $filteredMessages[] = $message->clone();
            }
        }
        return new Messages(...$filteredMessages);
    }

    public function count() : int {
        return count($this->messages);
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

    /**  @return Message[] */
    public function all() : array {
        return $this->messages;
    }
}

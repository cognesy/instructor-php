<?php declare(strict_types=1);

namespace Cognesy\Messages\Traits\Message;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Contracts\CanProvideMessage;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\Utils\Image;
use Cognesy\Utils\TextRepresentation;

trait HandlesCreation
{
    public static function empty() {
        return new self(
            role: self::DEFAULT_ROLE,
            content: Content::empty(),
        );
    }

    public static function make(string $role, string|array|Content $content, string $name = '') : Message {
        return new Message(role: $role, content: $content, name: $name);
    }

    public static function asUser(string|array|Message $message, string $name = '') : static {
        return static::fromAny($message, MessageRole::User, $name);
    }

    public static function asAssistant(string|array|Message $message, string $name = '') : static {
        return static::fromAny($message, MessageRole::Assistant, $name);
    }

    public static function asSystem(string|array|Message $message, string $name = '') : static {
        return static::fromAny($message, MessageRole::System, $name);
    }

    public static function asDeveloper(string|array|Message $message, string $name = '') : static {
        return static::fromAny($message, MessageRole::Developer, $name);
    }

    public static function fromAny(
        string|array|Message|Content|ContentPart $message,
        string|MessageRole|null $role = null,
        string $name = ''
    ) : static {
        $message = match (true) {
            is_string($message) => new Message(role: $role, content: $message),
            is_array($message) => Message::fromArray($message),
            $message instanceof Message => $message->clone(),
            $message instanceof Content => Message::fromContent($message),
            $message instanceof ContentPart => Message::fromContentPart($message),
            default => throw new \InvalidArgumentException('Unsupported message type: ' . gettype($message)),
        };
        if ($name !== '') {
            $message = $message->withName($name);
        }
        return match (true) {
            $role === null => $message,
            default => $message->withRole(MessageRole::fromAny($role)),
        };
    }

    public static function fromString(
        string $content,
        string $role = self::DEFAULT_ROLE,
        string $name = ''
    ) : static {
        return new static(role: $role, content: $content, name: $name);
    }

    public static function fromArray(array $message) : static {
        $role = $message['role'] ?? 'user';
        $content = match(true) {
            self::isArrayOfStrings($message) => Content::fromAny(array_map(fn($text) => ContentPart::text($text), $message)),
            Message::isMessage($message) => Content::fromAny($message['content'] ?? ''),
            default => throw new \InvalidArgumentException('Invalid message array - must be an array of strings or a valid message structure'),
        };

        return new static(
            role: $role,
            content: $content,
            name: $message['name'] ?? '',
            metadata: $message['_metadata'] ?? [],
        );
    }

    public static function fromContent(Content $content, string|MessageRole|null $role = null) : static {
        return new static(
            role: $role,
            content: $content
        );
    }

    public static function fromContentPart(ContentPart $part, string|MessageRole|null $role = null) : static {
        return new static(
            role: $role,
            content: Content::empty()->addContentPart($part),
        );
    }

    public static function fromInput(string|array|object $input, string $role = '') : static {
        return match(true) {
            $input instanceof Message => $input,
            $input instanceof CanProvideMessage => $input->toMessage(),
            default => new Message($role, TextRepresentation::fromAny($input)),
        };
    }

    public static function fromImage(Image $image, string $role = '') : static {
        return new static(role: $role, content: $image->toContent());
    }

    public function clone() : self {
        return new static(
            role: $this->role,
            content: $this->content->clone(),
            name: $this->name,
            metadata: $this->metadata,
        );
    }

    private static function isArrayOfStrings(array $array): bool {
        return count($array) > 0 && array_reduce(
            $array,
            fn(bool $carry, $item) => $carry && is_string($item),
            true
        );
    }

    private static function isArrayOfMessageArrays(array $array): bool {
        return count($array) > 0 && array_reduce(
            $array,
            fn(bool $carry, $item) => $carry && is_array($item) && Message::isMessage($item),
            true
        );
    }

    private static function isArrayOfContent(array $array): bool {
        return count($array) > 0 && array_reduce(
            $array,
            fn(bool $carry, $item) => $carry && ($item instanceof Content),
            true
        );
    }

    private static function isArrayOfMessages(array $array): bool {
        return count($array) > 0 && array_reduce(
            $array,
            fn(bool $carry, $item) => $carry && ($item instanceof Message),
            true
        );
    }

    private static function isArrayOfContentParts(array $array): bool {
        return count($array) > 0 && array_reduce(
            $array,
            fn(bool $carry, $item) => $carry && ($item instanceof ContentPart),
            true
        );
    }
}
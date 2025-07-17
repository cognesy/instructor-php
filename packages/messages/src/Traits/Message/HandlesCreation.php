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
    public static function make(string $role, string|array|Content $content, string $name = '') : Message {
        return new Message(role: $role, content: $content, name: $name);
    }

    public static function fromAny(
        string|array|Message|Content|ContentPart $message,
        string|MessageRole|null $role = null
    ) : static {
        $message = match (true) {
            is_string($message) => new Message(role: $role, content: $message),
            is_array($message) => Message::fromArray($message),
            $message instanceof Message => $message->clone(),
            $message instanceof Content => Message::fromContent($message),
            $message instanceof ContentPart => Message::fromContentPart($message),
            default => throw new \InvalidArgumentException('Unsupported message type: ' . gettype($message)),
        };
        return match (true) {
            $role === null => $message,
            default => $message->withRole(MessageRole::fromAny($role)),
        };
    }

    public static function fromString(string $content, string $role = self::DEFAULT_ROLE) : static {
        return new static(role: $role, content: $content);
    }

    public static function fromArray(array $message) : static {
        $role = $message['role'] ?? 'user';
        $content = match(true) {
            self::isArrayOfStrings($message) => Content::fromAny(array_map(fn($text) => ContentPart::text($text), $message)),
            Message::isMessage($message) => Content::fromAny($message),
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

    public static function fromContentPart(ContentPart $part, string|MessageRole $role = null) : static {
        return new static(
            role: $role,
            content: (new Content)->addContentPart($part),
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
        $cloned = new static();
        $cloned->role = $this->role;
        $cloned->name = $this->name;
        $cloned->content = $this->content->clone();
        $cloned->metadata = $this->metadata;
        return $cloned;
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
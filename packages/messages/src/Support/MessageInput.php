<?php declare(strict_types=1);

namespace Cognesy\Messages\Support;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Contracts\CanProvideMessage;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Support\ContentInput;
use Cognesy\Utils\TextRepresentation;
use DateTimeImmutable;
use InvalidArgumentException;

final class MessageInput
{
    public static function fromAny(
        string|array|Message|Messages|Content|ContentPart|ContentParts $message,
        string|MessageRole|null $role = null,
        string $name = '',
    ): Message {
        $resolved = match (true) {
            is_string($message) => new Message(role: $role, content: $message),
            is_array($message) => self::fromArray($message),
            $message instanceof Message => $message->clone(),
            $message instanceof Content => new Message(role: $role, content: $message),
            $message instanceof ContentPart => new Message(
                role: $role,
                content: Content::empty()->addContentPart($message),
            ),
            $message instanceof ContentParts => new Message(
                role: $role,
                content: Content::fromParts($message),
            ),
            default => throw new InvalidArgumentException('Unsupported message type: ' . gettype($message)),
        };

        $withName = match (true) {
            $name === '' => $resolved,
            default => $resolved->withName($name),
        };

        return match (true) {
            $role === null => $withName,
            default => $withName->withRole(self::normalizeRole($role)),
        };
    }

    public static function fromArray(array $message): Message {
        $role = $message['role'] ?? Message::DEFAULT_ROLE;
        $content = match (true) {
            self::isArrayOfStrings($message) => ContentInput::fromAny(array_map(
                fn(string $text) => ContentPart::text($text),
                $message,
            )),
            Message::isMessage($message) => ContentInput::fromAny($message['content'] ?? ''),
            default => throw new InvalidArgumentException(
                'Invalid message array - must be an array of strings or a valid message structure'
            ),
        };

        return new Message(
            role: $role,
            content: $content,
            name: $message['name'] ?? '',
            metadata: $message['_metadata'] ?? $message['metadata'] ?? [],
            parentId: $message['parentId'] ?? null,
            // Identity fields for deserialization
            id: $message['id'] ?? null,
            createdAt: isset($message['createdAt']) ? new DateTimeImmutable($message['createdAt']) : null,
        );
    }

    public static function fromInput(string|array|object $input, string $role = ''): Message {
        return match (true) {
            $input instanceof Message => $input,
            $input instanceof CanProvideMessage => $input->toMessage(),
            default => new Message($role, TextRepresentation::fromAny($input)),
        };
    }

    private static function isArrayOfStrings(array $array): bool {
        return count($array) > 0 && array_reduce(
            $array,
            fn(bool $carry, $item) => $carry && is_string($item),
            true,
        );
    }

    private static function normalizeRole(string|MessageRole $role): MessageRole {
        return match (true) {
            $role instanceof MessageRole => $role,
            default => MessageRole::tryFromString($role) ?? MessageRole::User,
        };
    }
}

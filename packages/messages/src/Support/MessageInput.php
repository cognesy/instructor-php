<?php declare(strict_types=1);

namespace Cognesy\Messages\Support;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Contracts\CanProvideMessage;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\MessageId;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Support\ContentInput;
use Cognesy\Messages\ToolCalls;
use Cognesy\Messages\ToolResult;
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
            $message instanceof Message => $message,
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

        $metadata = $message['_metadata'] ?? $message['metadata'] ?? [];

        // Hydrate tool_calls: from top-level 'tool_calls', or legacy _metadata.tool_calls
        $toolCallsRaw = $message['tool_calls'] ?? $metadata['tool_calls'] ?? null;
        $toolCalls = match (true) {
            $toolCallsRaw instanceof ToolCalls => $toolCallsRaw,
            is_array($toolCallsRaw) && $toolCallsRaw !== [] => ToolCalls::fromArray($toolCallsRaw),
            default => null,
        };

        // Hydrate tool_result: from top-level 'tool_result', or legacy _metadata.tool_result
        $toolResultRaw = $message['tool_result'] ?? $metadata['tool_result'] ?? null;
        $toolResult = match (true) {
            $toolResultRaw instanceof ToolResult => $toolResultRaw,
            is_array($toolResultRaw) && $toolResultRaw !== [] => ToolResult::fromArray($toolResultRaw),
            default => null,
        };

        // Remove tool data from metadata — it lives on the Message now
        if (is_array($metadata)) {
            unset($metadata['tool_calls'], $metadata['tool_result']);
        }

        return new Message(
            role: $role,
            content: $content,
            name: $message['name'] ?? '',
            metadata: $metadata,
            parentId: isset($message['parentId']) ? new MessageId((string)$message['parentId']) : null,
            toolCalls: $toolCalls,
            toolResult: $toolResult,
            id: isset($message['id']) ? new MessageId((string)$message['id']) : null,
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

<?php declare(strict_types=1);

namespace Cognesy\Messages\Support;

use Cognesy\Messages\Contracts\CanProvideMessage;
use Cognesy\Messages\Contracts\CanProvideMessages;
use Cognesy\Messages\Message;
use Cognesy\Messages\MessageList;
use Cognesy\Messages\Messages;
use Cognesy\Utils\TextRepresentation;
use InvalidArgumentException;

final class MessagesInput
{
    /**
     * @param list<string|array<array-key, mixed>> $messages List of messages (strings or arrays with role/content)
     */
    public static function fromArray(array $messages): Messages {
        $newMessages = [];
        foreach ($messages as $message) {
            $newMessages[] = match (true) {
                is_string($message) => Message::fromString($message),
                Message::isMessage($message) => MessageInput::fromArray($message),
                default => throw new InvalidArgumentException(
                    'Invalid message array - missing role or content keys'
                ),
            };
        }
        return new Messages(...$newMessages);
    }

    public static function fromAnyArray(array $messages): Messages {
        if (Message::isMessage($messages)) {
            return self::fromArray([$messages]);
        }
        $newMessages = [];
        foreach ($messages as $message) {
            $newMessages[] = match (true) {
                is_array($message) => match (true) {
                    Message::isMessage($message) => MessageInput::fromArray($message),
                    default => throw new InvalidArgumentException(
                        'Invalid message array - missing role or content keys'
                    ),
                },
                is_string($message) => new Message('user', $message),
                $message instanceof Message => $message,
                default => throw new InvalidArgumentException('Invalid message type'),
            };
        }
        return new Messages(...$newMessages);
    }

    public static function fromAny(string|array|Message|Messages|MessageList $messages): Messages {
        return match (true) {
            is_string($messages) => Messages::fromString($messages),
            is_array($messages) => self::fromAnyArray($messages),
            $messages instanceof Message => new Messages($messages),
            $messages instanceof Messages => $messages,
            $messages instanceof MessageList => new Messages(...$messages->all()),
            default => throw new InvalidArgumentException('Invalid message type'),
        };
    }

    public static function fromInput(string|array|object $input): Messages {
        return match (true) {
            $input instanceof Messages => $input,
            $input instanceof MessageList => new Messages(...$input->all()),
            $input instanceof CanProvideMessages => $input->toMessages(),
            $input instanceof Message => new Messages($input),
            $input instanceof CanProvideMessage => new Messages($input->toMessage()),
            default => new Messages(new Message(role: 'user', content: TextRepresentation::fromAny($input))),
        };
    }
}

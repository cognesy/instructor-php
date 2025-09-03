<?php declare(strict_types=1);

namespace Cognesy\Messages\Traits\Messages;

use Cognesy\Messages\Contracts\CanProvideMessage;
use Cognesy\Messages\Contracts\CanProvideMessages;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Utils\TextRepresentation;
use Exception;
use InvalidArgumentException;

trait HandlesCreation
{
    static public function fromString(string $content, string $role = 'user') : Messages {
        return new Messages(Message::fromString($content, $role));
    }

    /** @param array<string, string|array> $messages */
    static public function fromArray(array $messages) : Messages {
        $newMessages = [];
        foreach ($messages as $message) {
            $newMessages[] = match(true) {
                is_string($message) => Message::fromString($message),
                Message::hasRoleAndContent($message) => Message::fromArray($message),
                default => throw new Exception('Invalid message array - missing role or content keys'),
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
                    default => throw new Exception('Invalid message array - missing role or content keys'),
                },
                is_string($message) => new Message('user', $message),
                $message instanceof Message => $message,
                default => throw new Exception('Invalid message type'),
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
            default => throw new Exception('Invalid message type'),
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
}

<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Messages;

use Cognesy\Instructor\Contracts\CanProvideMessage\CanProvideMessage;
use Cognesy\Instructor\Contracts\CanProvideMessages;
use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Data\Messages\Utils\Text;
use Exception;
use InvalidArgumentException;

trait HandlesCreation
{
    static public function fromString(string $content) : Messages {
        return (new self)->appendMessage(Message::fromString($content));
    }

    /**
     * @param array<string, string|array> $messages
     */
    static public function fromArray(array $messages) : Messages {
        $instance = new self();
        foreach ($messages as $message) {
            $instance->messages[] = match(true) {
                is_string($message) => Message::fromString($message),
                Message::hasRoleAndContent($message) => new Message($message['role'], $message['content']),
                default => throw new Exception('Invalid message array - missing role or content keys'),
            };
        }
        return $instance;
    }

    /**
     * @param Messages[] $messages
     */
    static public function fromMessages(array|Message|Messages ...$arrayOfMessages) : Messages {
        $instance = new self();
        foreach ($arrayOfMessages as $message) {
            if ($message instanceof Messages) {
                $instance->appendMessages($message);
            } elseif ($message instanceof Message) {
                $instance->appendMessage($message);
            } elseif (is_array($message)) {
                $instance->appendMessage(Message::fromArray($message));
            } else {
                throw new InvalidArgumentException('Invalid type for message');
            }
        }
        return $instance;
    }

    public static function fromAnyArray(array $messages) : Messages {
        if (Message::hasRoleAndContent($messages)) {
            return self::fromArray([$messages]);
        }
        $normalized = new self();
        foreach ($messages as $message) {
            $normalized->appendMessage(match(true) {
                is_array($message) => match(true) {
                    Message::hasRoleAndContent($message) => new Message($message['role'], $message['content']),
                    default => throw new Exception('Invalid message array - missing role or content keys'),
                },
                is_string($message) => new Message('user', $message),
                $message instanceof Message => $message,
                default => throw new Exception('Invalid message type'),
            });
        }
        return $normalized;
    }

    public static function fromAny(string|array|Message|Messages $messages) : Messages {
        return match(true) {
            is_string($messages) => self::fromString($messages),
            is_array($messages) => self::fromAnyArray($messages),
            $messages instanceof Message => (new Messages)->appendMessage($messages),
            $messages instanceof Messages => $messages,
            default => throw new Exception('Invalid message type'),
        };
    }

    public static function fromInput(string|array|object $input) : static {
        return match(true) {
            $input instanceof Messages => $input,
            $input instanceof CanProvideMessages => $input->toMessages(),
            $input instanceof Message => (new Messages)->appendMessage($input),
            $input instanceof CanProvideMessage => (new Messages)->appendMessage($input->toMessage()),
            default => (new Messages)->appendMessage(new Message('user', Text::fromAny($input))),
        };
    }
}

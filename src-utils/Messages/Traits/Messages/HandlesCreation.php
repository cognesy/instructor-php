<?php

namespace Cognesy\Utils\Messages\Traits\Messages;

use Cognesy\Utils\Messages\Contracts\CanProvideMessage;
use Cognesy\Utils\Messages\Contracts\CanProvideMessages;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\Messages\Utils\TextRepresentation;
use Exception;
use InvalidArgumentException;

trait HandlesCreation
{
    static public function fromString(string $content, string $role = 'user') : \Cognesy\Utils\Messages\Messages {
        return (new self)->appendMessage(\Cognesy\Utils\Messages\Message::fromString($content, $role));
    }

    /**
     * @param array<string, string|array> $messages
     */
    static public function fromArray(array $messages) : \Cognesy\Utils\Messages\Messages {
        $instance = new self();
        foreach ($messages as $message) {
            $instance->messages[] = match(true) {
                is_string($message) => \Cognesy\Utils\Messages\Message::fromString($message),
                \Cognesy\Utils\Messages\Message::hasRoleAndContent($message) => Message::fromArray($message),
                default => throw new Exception('Invalid message array - missing role or content keys'),
            };
        }
        return $instance;
    }

    /**
     * @param \Cognesy\Utils\Messages\Messages[] $messages
     */
    static public function fromMessages(array|\Cognesy\Utils\Messages\Message|\Cognesy\Utils\Messages\Messages ...$arrayOfMessages) : Messages {
        $instance = new self();
        foreach ($arrayOfMessages as $message) {
            if ($message instanceof Messages) {
                $instance->appendMessages($message);
            } elseif ($message instanceof \Cognesy\Utils\Messages\Message) {
                $instance->appendMessage($message);
            } elseif (is_array($message)) {
                $instance->appendMessage(\Cognesy\Utils\Messages\Message::fromArray($message));
            } else {
                throw new InvalidArgumentException('Invalid type for message');
            }
        }
        return $instance;
    }

    public static function fromAnyArray(array $messages) : \Cognesy\Utils\Messages\Messages {
        if (\Cognesy\Utils\Messages\Message::hasRoleAndContent($messages)) {
            return self::fromArray([$messages]);
        }
        $normalized = new self();
        foreach ($messages as $message) {
            $normalized->appendMessage(match(true) {
                is_array($message) => match(true) {
                    \Cognesy\Utils\Messages\Message::hasRoleAndContent($message) => Message::fromArray($message),
                    default => throw new Exception('Invalid message array - missing role or content keys'),
                },
                is_string($message) => new \Cognesy\Utils\Messages\Message('user', $message),
                $message instanceof \Cognesy\Utils\Messages\Message => $message,
                default => throw new Exception('Invalid message type'),
            });
        }
        return $normalized;
    }

    public static function fromAny(string|array|\Cognesy\Utils\Messages\Message|\Cognesy\Utils\Messages\Messages $messages) : \Cognesy\Utils\Messages\Messages {
        return match(true) {
            is_string($messages) => self::fromString($messages),
            is_array($messages) => self::fromAnyArray($messages),
            $messages instanceof \Cognesy\Utils\Messages\Message => (new \Cognesy\Utils\Messages\Messages)->appendMessage($messages),
            $messages instanceof \Cognesy\Utils\Messages\Messages => $messages,
            default => throw new Exception('Invalid message type'),
        };
    }

    public static function fromInput(string|array|object $input) : static {
        return match(true) {
            $input instanceof \Cognesy\Utils\Messages\Messages => $input,
            $input instanceof CanProvideMessages => $input->toMessages(),
            $input instanceof \Cognesy\Utils\Messages\Message => (new \Cognesy\Utils\Messages\Messages)->appendMessage($input),
            $input instanceof CanProvideMessage => (new \Cognesy\Utils\Messages\Messages)->appendMessage($input->toMessage()),
            default => (new \Cognesy\Utils\Messages\Messages)->appendMessage(new Message('user', TextRepresentation::fromAny($input))),
        };
    }
}

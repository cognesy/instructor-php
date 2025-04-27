<?php

namespace Cognesy\Utils\Messages\Traits\Messages;

use Cognesy\Utils\Messages\Contracts\CanProvideMessage;
use Cognesy\Utils\Messages\Contracts\CanProvideMessages;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\TextRepresentation;
use Exception;
use InvalidArgumentException;

trait HandlesCreation
{
    static public function fromString(string $content, string $role = 'user') : Messages {
        return (new self)->appendMessage(\Cognesy\Utils\Messages\Message::fromString($content, $role));
    }

    /**
     * @param array<string, string|array> $messages
     */
    static public function fromArray(array $messages) : Messages {
        $instance = new self();
        foreach ($messages as $message) {
            $instance->messages[] = match(true) {
                is_string($message) => Message::fromString($message),
                \Cognesy\Utils\Messages\Message::hasRoleAndContent($message) => \Cognesy\Utils\Messages\Message::fromArray($message),
                default => throw new Exception('Invalid message array - missing role or content keys'),
            };
        }
        return $instance;
    }

    /**
     * @param array|\Cognesy\Utils\Messages\Message[]|\Cognesy\Utils\Messages\Messages $messages
     */
    static public function fromMessages(array|\Cognesy\Utils\Messages\Messages $arrayOfMessages) : \Cognesy\Utils\Messages\Messages {
        $instance = new self();
        foreach ($arrayOfMessages as $message) {
            if ($message instanceof \Cognesy\Utils\Messages\Messages) {
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
        if (\Cognesy\Utils\Messages\Message::hasRoleAndContent($messages)) {
            return self::fromArray([$messages]);
        }
        $normalized = new self();
        foreach ($messages as $message) {
            $normalized->appendMessage(match(true) {
                is_array($message) => match(true) {
                    Message::hasRoleAndContent($message) => \Cognesy\Utils\Messages\Message::fromArray($message),
                    default => throw new Exception('Invalid message array - missing role or content keys'),
                },
                is_string($message) => new \Cognesy\Utils\Messages\Message('user', $message),
                $message instanceof \Cognesy\Utils\Messages\Message => $message,
                default => throw new Exception('Invalid message type'),
            });
        }
        return $normalized;
    }

    public static function fromAny(string|array|\Cognesy\Utils\Messages\Message|\Cognesy\Utils\Messages\Messages $messages) : Messages {
        return match(true) {
            is_string($messages) => self::fromString($messages),
            is_array($messages) => self::fromAnyArray($messages),
            $messages instanceof \Cognesy\Utils\Messages\Message => (new \Cognesy\Utils\Messages\Messages)->appendMessage($messages),
            $messages instanceof Messages => $messages,
            default => throw new Exception('Invalid message type'),
        };
    }

    public static function fromInput(string|array|object $input) : static {
        return match(true) {
            $input instanceof Messages => $input,
            $input instanceof CanProvideMessages => $input->toMessages(),
            $input instanceof Message => (new \Cognesy\Utils\Messages\Messages)->appendMessage($input),
            $input instanceof CanProvideMessage => (new Messages)->appendMessage($input->toMessage()),
            default => (new Messages)->appendMessage(new \Cognesy\Utils\Messages\Message('user', TextRepresentation::fromAny($input))),
        };
    }
}

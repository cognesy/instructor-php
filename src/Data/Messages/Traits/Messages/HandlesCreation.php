<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Messages;

use BackedEnum;
use Closure;
use Cognesy\Instructor\Contracts\CanProvideMessage\CanProvideMessage;
use Cognesy\Instructor\Contracts\CanProvideMessages;
use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Data\Messages\Utils\Text;
use Cognesy\Instructor\Utils\Json;
use InvalidArgumentException;

trait HandlesCreation
{
    static public function fromString(string $role = 'user', string $content = '') : Messages {
        return (new self)->appendMessage(new Message($role, $content));
    }

    /**
     * @param array<string, string|array> $messages
     */
    static public function fromArray(array $messages) : Messages {
        $instance = new self();
        foreach ($messages as $message) {
            $instance->messages[] = new Message($message['role'], $message['content']);
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

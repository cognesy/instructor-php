<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Messages;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
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
                $instance->appendMessage(new Message($message['role'], $message['content']));
            } else {
                throw new InvalidArgumentException('Invalid type for message');
            }
        }
        return $instance;
    }
}
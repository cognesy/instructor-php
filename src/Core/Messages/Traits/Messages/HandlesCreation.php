<?php

namespace Cognesy\Instructor\Core\Messages\Traits\Messages;

use Cognesy\Instructor\Core\Messages\Message;
use Cognesy\Instructor\Core\Messages\Messages;
use InvalidArgumentException;

trait HandlesCreation
{
    /**
     * @param array<string, string|array> $messages
     */
    static public function fromArray(array $messages) : Messages
    {
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
                $instance->add($message);
            } elseif (is_array($message)) {
                $instance->add(new Message($message['role'], $message['content']));
            } else {
                throw new InvalidArgumentException('Invalid type for message');
            }
        }
        return $instance;
    }
}
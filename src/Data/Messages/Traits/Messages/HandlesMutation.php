<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Messages;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;

trait HandlesMutation
{
    public function appendMessage(array|Message $message) : static {
        $this->messages[] = match (true) {
            is_array($message) => new Message($message['role'], $message['content']),
            default => $message,
        };
        return $this;
    }

    public function appendMessages(array|Messages $messages) : static {
        if ($messages instanceof Messages) {
            $this->messages = array_merge($this->messages, $messages->messages);
        } else {
            foreach ($messages as $message) {
                $this->messages[] = new Message($message['role'], $message['content']);
            }
        }
        return $this;
    }

    public function prependMessages(array|Messages $messages) : static {
        if ($messages instanceof Messages) {
            $this->messages = array_merge($messages->messages, $this->messages);
        } else {
            $prepended = [];
            foreach ($messages as $message) {
                $prepended = new Message($message['role'], $message['content']);
            }
            $this->messages = array_merge($prepended, $this->messages);
        }
        return $this;
    }

    public function removeHead() : static {
        array_shift($this->messages);
        return $this;
    }

    public function removeTail() : static {
        array_pop($this->messages);
        return $this;
    }
}
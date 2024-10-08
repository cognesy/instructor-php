<?php
namespace Cognesy\Instructor\Utils\Messages\Traits\Messages;

use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;

trait HandlesMutation
{
    public function setMessage(string|array|Message $message) : static {
        $this->messages = match (true) {
            is_string($message) => [\Cognesy\Instructor\Utils\Messages\Message::fromString($message)],
            is_array($message) => [\Cognesy\Instructor\Utils\Messages\Message::fromArray($message)],
            default => [$message],
        };
        return $this;
    }

    public function appendMessage(array|\Cognesy\Instructor\Utils\Messages\Message $message) : static {
        $this->messages[] = match (true) {
            is_array($message) => Message::fromArray($message),
            default => $message,
        };
        return $this;
    }

    public function appendMessages(array|Messages $messages) : static {
        $appended = match (true) {
            $messages instanceof Messages => $messages->messages,
            default => Messages::fromAnyArray($messages)->messages,
        };
        $this->messages = array_merge($this->messages, $appended);
        return $this;
    }

    public function prependMessages(array|Messages $messages) : static {
        $this->messages = match (true) {
            empty($messages) => $this->messages,
            $messages instanceof Messages => array_merge($messages->messages, $this->messages),
            default => array_merge(Messages::fromAnyArray($messages)->messages, $this->messages),
        };
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
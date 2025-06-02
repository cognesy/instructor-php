<?php
namespace Cognesy\Utils\Messages\Traits\Messages;

use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

trait HandlesMutation
{
    public function withMessage(string|array|Message $message) : static {
        $this->messages = match (true) {
            is_string($message) => [Message::fromString($message)],
            is_array($message) => [Message::fromArray($message)],
            default => [$message],
        };
        return $this;
    }

    public function withMessages(array|Messages $messages) : static {
        $this->messages = match (true) {
            $messages instanceof Messages => $messages->messages,
            default => Messages::fromAnyArray($messages)->messages,
        };
        return $this;
    }

    public function appendMessage(string|array|Message $message) : static {
        $this->messages[] = match (true) {
            is_string($message) => Message::fromString($message),
            is_array($message) => Message::fromArray($message),
            default => $message,
        };
        return $this;
    }

    public function appendMessages(array|Messages $messages) : static {
        if (Messages::becomesEmpty($messages)) {
            return $this;
        }
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

    public function prependMessage(Message $param) : static {
        $this->prependMessages([$param]);
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

    public function appendContentFields(array $fields) : static {
        $lastMessage = end($this->messages);
        if (!$lastMessage) {
            return $this;
        }

        $lastMessage->content()->appendContentFields($fields);
        return $this;
    }

    public function appendContentField(string $key, mixed $value) : static {
        $lastMessage = end($this->messages);
        if (!$lastMessage) {
            return $this;
        }

        $lastMessage->content()->appendContentField($key, $value);
        return $this;
    }
}
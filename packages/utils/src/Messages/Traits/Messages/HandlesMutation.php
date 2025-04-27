<?php
namespace Cognesy\Utils\Messages\Traits\Messages;

trait HandlesMutation
{
    public function withMessage(string|array|\Cognesy\Utils\Messages\Message $message) : static {
        $this->messages = match (true) {
            is_string($message) => [\Cognesy\Utils\Messages\Message::fromString($message)],
            is_array($message) => [\Cognesy\Utils\Messages\Message::fromArray($message)],
            default => [$message],
        };
        return $this;
    }

    public function withMessages(array|\Cognesy\Utils\Messages\Messages $messages) : static {
        $this->messages = match (true) {
            $messages instanceof \Cognesy\Utils\Messages\Messages => $messages->messages,
            default => \Cognesy\Utils\Messages\Messages::fromAnyArray($messages)->messages,
        };
        return $this;
    }

    public function appendMessage(string|array|\Cognesy\Utils\Messages\Message $message) : static {
        $this->messages[] = match (true) {
            is_string($message) => \Cognesy\Utils\Messages\Message::fromString($message),
            is_array($message) => \Cognesy\Utils\Messages\Message::fromArray($message),
            default => $message,
        };
        return $this;
    }

    public function appendMessages(array|\Cognesy\Utils\Messages\Messages $messages) : static {
        if (\Cognesy\Utils\Messages\Messages::becomesEmpty($messages)) {
            return $this;
        }
        $appended = match (true) {
            $messages instanceof \Cognesy\Utils\Messages\Messages => $messages->messages,
            default => \Cognesy\Utils\Messages\Messages::fromAnyArray($messages)->messages,
        };
        $this->messages = array_merge($this->messages, $appended);
        return $this;
    }

    public function prependMessages(array|\Cognesy\Utils\Messages\Messages $messages) : static {
        $this->messages = match (true) {
            empty($messages) => $this->messages,
            $messages instanceof \Cognesy\Utils\Messages\Messages => array_merge($messages->messages, $this->messages),
            default => array_merge(\Cognesy\Utils\Messages\Messages::fromAnyArray($messages)->messages, $this->messages),
        };
        return $this;
    }

    public function prependMessage(\Cognesy\Utils\Messages\Message $param) : static {
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
}
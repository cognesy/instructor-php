<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Messages;

use Cognesy\Instructor\Data\Messages\Enums\MessageRole;
use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;

trait HandlesAccess
{
    public function first() : Message {
        if (empty($this->messages)) {
            return new Message();
        }
        return $this->messages[0];
    }

    public function last() : Message {
        if (empty($this->messages)) {
            return new Message();
        }
        return $this->messages[count($this->messages)-1];
    }

    public function hasComposites() : bool {
        return $this->reduce(fn(bool $carry, Message $message) => $carry || $message->isComposite(), false);
    }

    public function middle() : Messages {
        if (count($this->messages) < 3) {
            return new Messages();
        }
        return Messages::fromMessages(array_slice($this->messages, 1, count($this->messages)-2));
    }

    public function head() : array {
        if (empty($this->messages)) {
            return [];
        }
        return array_slice($this->messages, 0, 1);
    }

    public function tail() : array {
        if (empty($this->messages)) {
            return [];
        }
        return array_slice($this->messages, count($this->messages)-1);
    }

    public function isEmpty() : bool {
        return match(true) {
            empty($this->messages) => true,
            default => $this->reduce(fn(bool $carry, Message $message) => $carry && $message->isEmpty(), true),
        };
    }

    public function notEmpty() : bool {
        return !$this->isEmpty();
    }

    public function reduce(callable $callback, mixed $initial = null) : mixed {
        return array_reduce($this->messages, $callback, $initial);
    }

    public function map(callable $callback) : array {
        return array_map($callback, $this->messages);
    }

    public function filter(callable $callback = null) : Messages {
        $messages = new Messages();
        foreach ($this->messages as $message) {
            if ($message->isEmpty()) {
                continue;
            }
            if ($callback($message)) {
                $messages->messages[] = $message->clone();
            }
        }
        return $messages;
    }

    // CONVENIENCE METHODS ///////////////////////////////////////////////////

    public function firstRole() : MessageRole {
        return $this->first()?->role();
    }

    public function lastRole() : MessageRole {
        return $this->last()?->role();
    }

    public function firstContent() : string|array {
        return $this->first()?->content();
    }

    public function lastContent() : string|array {
        return $this->last()?->content();
    }
}
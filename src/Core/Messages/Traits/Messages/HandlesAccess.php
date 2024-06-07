<?php
namespace Cognesy\Instructor\Core\Messages\Traits\Messages;

use Cognesy\Instructor\Core\Messages\Enums\MessageRole;
use Cognesy\Instructor\Core\Messages\Message;
use Cognesy\Instructor\Core\Messages\Messages;

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
        return empty($this->messages);
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
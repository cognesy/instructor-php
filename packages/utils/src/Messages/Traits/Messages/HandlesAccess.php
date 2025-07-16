<?php declare(strict_types=1);

namespace Cognesy\Utils\Messages\Traits\Messages;

use Cognesy\Utils\Messages\Enums\MessageRole;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Generator;

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

    /**
     * @return Generator<Message>
     */
    public function each() : iterable {
        foreach ($this->messages as $message) {
            yield $message;
        }
    }

    public function hasComposites() : bool {
        return $this->reduce(fn(bool $carry, Message $message) => $carry || $message->isComposite(), false);
    }

    public function middle() : Messages {
        $messageCount = count($this->messages);
        if ($messageCount < 3) {
            return new Messages();
        }
        $slice = array_slice($this->messages, 1, $messageCount - 2);
        return Messages::fromMessages($slice);
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

    public static function becomesEmpty(array|Message|Messages $messages) : bool {
        return match(true) {
            is_array($messages) && empty($messages) => true,
            $messages instanceof Message => $messages->isEmpty(),
            $messages instanceof Messages => $messages->isEmpty(),
            default => false,
        };
    }

    public static function becomesComposite(array $messages) : bool {
        return match(true) {
            empty($messages) => false,
            default => Messages::fromMessages($messages)->hasComposites(),
        };
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

    public function filter(?callable $callback = null) : Messages {
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

    public function count() : int {
        return count($this->messages);
    }

    // CONVENIENCE METHODS ///////////////////////////////////////////////////

    public function firstRole() : MessageRole {
        return $this->first()?->role();
    }

    public function lastRole() : MessageRole {
        return $this->last()?->role();
    }

    /**  @return Message[] */
    public function all() : array {
        return $this->messages;
    }
}
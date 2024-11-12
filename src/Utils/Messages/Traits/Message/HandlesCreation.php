<?php

namespace Cognesy\Instructor\Utils\Messages\Traits\Message;

use Cognesy\Instructor\Contracts\CanProvideMessage;
use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Utils\Text;
use Exception;
use InvalidArgumentException;

trait HandlesCreation
{
    public static function make(string $role, string|array $content) : Message {
        return new Message($role, $content);
    }

    public static function fromString(string $content) : static {
        return new static(self::DEFAULT_ROLE, $content);
    }

    public static function fromArray(array $message) : static {
        if (!self::hasRoleAndContent($message)) {
            throw new InvalidArgumentException('Message array must contain "role" and "content" keys');
        }
        return new static($message['role'], $message['content']);
    }

    public static function fromContent(string $role, string|array $content) : static {
        return new static($role, $content);
    }

    public static function fromAnyMessage(string|array|Message $message) : static {
        return match(true) {
            is_string($message) => static::fromString($message),
            is_array($message) => static::fromArray($message),
            $message instanceof static => $message->clone(),
            default => throw new Exception('Invalid message type'),
        };
    }

    public static function fromInput(string|array|object $input, string $role = '') : static {
        return match(true) {
            $input instanceof Message => $input,
            $input instanceof CanProvideMessage => $input->toMessage(),
            default => new Message($role, Text::fromAny($input)),
        };
    }

    public function clone() : static {
        return new static($this->role, $this->content);
    }
}
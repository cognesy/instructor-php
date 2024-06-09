<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Message;

use BackedEnum;
use Closure;
use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Utils\Json;
use Exception;
use InvalidArgumentException;

trait HandlesCreation
{
    public static function make(string $role, string|array $content) : static {
        return new static($role, $content);
    }

    public static function fromArray(array $message) : static {
        if (!self::hasRoleAndContent($message)) {
            throw new InvalidArgumentException('Message array must contain "role" and "content" keys');
        }
        return new static($message['role'], $message['content']);
    }

    public static function fromContent(string|array $content) : static {
        return new static('user', $content);
    }

    public static function fromAnyMessage(string|array|Message $message) : static {
        return match(true) {
            is_array($message) => static::fromArray($message),
            is_string($message) => static::fromContent($message),
            $message instanceof static => $message->clone(),
            default => throw new Exception('Invalid message type'),
        };
    }

    public static function fromInput(string|array|object $input, string $role = 'user') : static {
        // TODO: is there a way to consolidate value rendering?
        $content = match(true) {
            is_string($input) => $input,
            is_array($input) => Json::encode($input),
            $input instanceof Example => $input->inputString(), // TODO: avoid recursion
            $input instanceof BackedEnum => $input->value,
            $input instanceof Closure => $input(),
            method_exists($input, 'toJson') => match(true) {
                is_string($input->toJson()) => $input->toJson(),
                default => Json::encode($input->toJson()),
            },
            method_exists($input, 'toArray') => Json::encode($input->toArray()),
            method_exists($input, 'toString') => $input->toString(),
            // ...how do we handle chat messages input?
            default => Json::encode($input), // fallback - just encode as JSON
        };
        return new static($role, $content);
    }

    public function clone() : static {
        return new static($this->role, $this->content);
    }

    private static function hasRoleAndContent(array $message) : bool {
        return isset($message['role'], $message['content']);
    }
}
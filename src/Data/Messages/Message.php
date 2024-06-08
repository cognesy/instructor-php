<?php
namespace Cognesy\Instructor\Data\Messages;

use InvalidArgumentException;

class Message {
    use Traits\Message\HandlesAccess;
    use Traits\Message\HandlesTransformation;

    /**
     * @param string $role
     * @param string|array<string|array> $content
     */
    public function __construct(
        public string $role = '',
        public string|array $content = '',
    ) {}

    public function clone() : Message {
        return new Message($this->role, $this->content);
    }

    static public function fromArray(array $message) : Message {
        if (!isset($message['role']) || !isset($message['content'])) {
            throw new InvalidArgumentException('Message array must contain "role" and "content" keys');
        }
        return new Message($message['role'], $message['content']);
    }
}

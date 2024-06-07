<?php
namespace Cognesy\Instructor\Core\Messages;

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
}

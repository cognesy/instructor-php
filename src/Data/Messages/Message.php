<?php
namespace Cognesy\Instructor\Data\Messages;

class Message {
    use Traits\Message\HandlesCreation;
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
}

<?php
namespace Cognesy\Instructor\Utils\Messages;

use Cognesy\Instructor\Utils\Messages\Enums\MessageRole;
use Cognesy\Instructor\Utils\Uuid;

class Message {
    use Traits\Message\HandlesCreation;
    use Traits\Message\HandlesMutation;
    use Traits\Message\HandlesAccess;
    use Traits\Message\HandlesTransformation;

    public const DEFAULT_ROLE = 'user';

    protected string $id;
    protected string $role;
    protected string|array $content;
    protected array $metadata = [];

    /**
     * @param string $role
     * @param string|array<string|array> $content
     */
    public function __construct(
        string|MessageRole $role = '',
        string|array|null $content = '',
        array $metadata = [],
    ) {
        $this->id = Uuid::uuid4();
        $this->role = match(true) {
            $role instanceof MessageRole => $role->value,
            ($role === '') => self::DEFAULT_ROLE,
            default => $role,
        };
        $this->content = $content ?? '';
        $this->metadata = $metadata;
    }
}

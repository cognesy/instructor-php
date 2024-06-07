<?php
namespace Cognesy\Instructor\Core\Messages;

class Messages {
    use Traits\Messages\HandlesCreation;
    use Traits\Messages\HandlesAccess;
    use Traits\Messages\HandlesMutation;
    use Traits\Messages\HandlesTransformation;

    /** @var Message[] $messages */
    private array $messages = [];
}

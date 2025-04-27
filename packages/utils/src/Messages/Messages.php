<?php
namespace Cognesy\Utils\Messages;

use Cognesy\Utils\Messages\Traits\Messages\HandlesAccess;
use Cognesy\Utils\Messages\Traits\Messages\HandlesConversion;
use Cognesy\Utils\Messages\Traits\Messages\HandlesCreation;
use Cognesy\Utils\Messages\Traits\Messages\HandlesMutation;
use Cognesy\Utils\Messages\Traits\Messages\HandlesTransformation;

class Messages {
    use HandlesAccess;
    use HandlesConversion;
    use HandlesCreation;
    use HandlesMutation;
    use HandlesTransformation;

    /** @var Message[] $messages */
    private array $messages = [];
}

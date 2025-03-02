<?php
namespace Cognesy\Utils\Messages;

use Cognesy\Utils\Messages\Traits\Messages\HandlesAccess;
use Cognesy\Utils\Messages\Traits\Messages\HandlesConversion;
use Cognesy\Utils\Messages\Traits\Messages\HandlesCreation;
use Cognesy\Utils\Messages\Traits\Messages\HandlesMutation;
use Cognesy\Utils\Messages\Traits\Messages\HandlesTransformation;

class Messages {
    use HandlesConversion;
    use HandlesCreation;
    use HandlesAccess;
    use HandlesMutation;
    use HandlesTransformation;

    /** @var Message[] $messages */
    private array $messages = [];
}

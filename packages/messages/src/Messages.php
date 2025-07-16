<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Messages\Traits\Messages\HandlesAccess;
use Cognesy\Messages\Traits\Messages\HandlesConversion;
use Cognesy\Messages\Traits\Messages\HandlesCreation;
use Cognesy\Messages\Traits\Messages\HandlesMutation;
use Cognesy\Messages\Traits\Messages\HandlesTransformation;

class Messages {
    use HandlesAccess;
    use HandlesConversion;
    use HandlesCreation;
    use HandlesMutation;
    use HandlesTransformation;

    /** @var Message[] $messages */
    private array $messages = [];
}

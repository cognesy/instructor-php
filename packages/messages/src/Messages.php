<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Messages\Traits\Messages\HandlesAccess;
use Cognesy\Messages\Traits\Messages\HandlesConversion;
use Cognesy\Messages\Traits\Messages\HandlesCreation;
use Cognesy\Messages\Traits\Messages\HandlesMutation;
use Cognesy\Messages\Traits\Messages\HandlesTransformation;

final readonly class Messages {
    use HandlesAccess;
    use HandlesConversion;
    use HandlesCreation;
    use HandlesMutation;
    use HandlesTransformation;

    /** @var Message[] $messages */
    private array $messages;

    /** @param Message[] $messages */
    public function __construct(Message ...$messages) {
        $this->messages = $messages;
    }

    public static function empty(): static {
        return new static();
    }
}

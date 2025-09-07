<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore;

use Cognesy\Messages\MessageStore\Traits\MessageStoreParameters\HandlesAccess;
use Cognesy\Messages\MessageStore\Traits\MessageStoreParameters\HandlesConversion;
use Cognesy\Messages\MessageStore\Traits\MessageStoreParameters\HandlesMutation;
use Cognesy\Messages\MessageStore\Traits\MessageStoreParameters\HandlesTransformation;

final readonly class MessageStoreParameters
{
    use HandlesAccess;
    use HandlesConversion;
    use HandlesMutation;
    use HandlesTransformation;

    public function __construct(
        private array $parameters = [],
    ) {}
}

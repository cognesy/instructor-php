<?php declare(strict_types=1);

namespace Cognesy\Messages\Contracts;

use Cognesy\Messages\Messages;

interface CanRenderMessages
{
    /**
     * Render variables into message contents, returning a new Messages instance.
     * Implementations should be pure and not mutate the input.
     *
     * @param array<string,mixed> $parameters
     */
    public function renderMessages(Messages $messages, array $parameters = []) : Messages;
}


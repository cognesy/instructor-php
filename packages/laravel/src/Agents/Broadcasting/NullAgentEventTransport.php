<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Agents\Broadcasting;

use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;

final class NullAgentEventTransport implements CanBroadcastAgentEvents
{
    #[\Override]
    public function broadcast(string $channel, array $envelope): void {}
}

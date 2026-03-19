<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Agents\Broadcasting;

use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;

final readonly class LaravelAgentEventTransport implements CanBroadcastAgentEvents
{
    public function __construct(
        private BroadcastingFactory $broadcasting,
        private ?string $connection = null,
        private string $eventName = 'instructor.agent.event',
    ) {}

    #[\Override]
    public function broadcast(string $channel, array $envelope): void
    {
        $this->broadcasting
            ->connection($this->connection)
            ->broadcast([$channel], $this->eventName, $envelope);
    }
}

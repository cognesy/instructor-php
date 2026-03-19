<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Testing;

use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;

final class RecordingAgentEventTransport implements CanBroadcastAgentEvents
{
    /** @var list<array{channel: string, envelope: array}> */
    private array $broadcasts = [];

    #[\Override]
    public function broadcast(string $channel, array $envelope): void
    {
        $this->broadcasts[] = [
            'channel' => $channel,
            'envelope' => $envelope,
        ];
    }

    /** @return list<array{channel: string, envelope: array}> */
    public function broadcasts(): array
    {
        return $this->broadcasts;
    }
}

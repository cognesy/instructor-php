<?php declare(strict_types=1);

namespace Cognesy\Agents\Broadcasting;

interface CanBroadcastAgentEvents
{
    public function broadcast(string $channel, array $envelope): void;
}

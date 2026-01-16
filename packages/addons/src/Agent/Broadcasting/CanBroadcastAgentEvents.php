<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Broadcasting;

interface CanBroadcastAgentEvents
{
    public function broadcast(string $channel, array $envelope): void;
}

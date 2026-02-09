<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Broadcasting;

interface CanBroadcastAgentEvents
{
    public function broadcast(string $channel, array $envelope): void;
}

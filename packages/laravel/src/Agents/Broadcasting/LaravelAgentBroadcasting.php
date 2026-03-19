<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Agents\Broadcasting;

use Cognesy\Agents\Broadcasting\AgentBroadcastObserver;
use Cognesy\Agents\Broadcasting\AgentEventBroadcaster;
use Cognesy\Agents\Broadcasting\BroadcastConfig;
use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;
use Cognesy\Agents\Capability\Broadcasting\UseAgentBroadcasting;

final readonly class LaravelAgentBroadcasting
{
    public function __construct(
        private CanBroadcastAgentEvents $transport,
        private BroadcastConfig $config,
    ) {}

    public function broadcaster(string $sessionId, string $executionId): AgentEventBroadcaster
    {
        return new AgentEventBroadcaster(
            broadcaster: $this->transport,
            sessionId: $sessionId,
            executionId: $executionId,
            config: $this->config,
        );
    }

    public function observer(?string $sessionId = null): AgentBroadcastObserver
    {
        return new AgentBroadcastObserver(
            transport: $this->transport,
            sessionId: $sessionId,
            config: $this->config,
        );
    }

    public function capability(?string $sessionId = null): UseAgentBroadcasting
    {
        return new UseAgentBroadcasting(
            broadcaster: $this->transport,
            sessionId: $sessionId,
            config: $this->config,
        );
    }
}

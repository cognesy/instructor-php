<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;
use Cognesy\Agents\Profile\AgentProfile;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;
use Cognesy\Events\Contracts\CanHandleEvents;

readonly final class TestAgentLoop extends AgentLoop
{
    private int $maxIterations;

    public function __construct(
        Tools $tools,
        CanExecuteToolCalls $toolExecutor,
        CanUseTools $driver,
        CanHandleEvents $events,
        ?CanInterceptAgentLifecycle $interceptor = null,
        AgentProfile $profile = new AgentProfile(
            identity: new \Cognesy\Agents\Profile\AgentIdentity('anonymous', ''),
            tools: new \Cognesy\Agents\Profile\ToolProfileList(),
            capabilities: new \Cognesy\Agents\Profile\CapabilityProfileList(),
            hooks: new \Cognesy\Agents\Profile\HookProfileList(),
        ),
        int $maxIterations = 1,
    ) {
        parent::__construct(
            tools: $tools,
            toolExecutor: $toolExecutor,
            driver: $driver,
            events: $events,
            interceptor: $interceptor,
            profile: $profile,
        );
        $this->maxIterations = $maxIterations;
    }

    #[\Override]
    protected function shouldStop(AgentState $state): bool
    {
        return $state->stepCount() >= $this->maxIterations;
    }
}

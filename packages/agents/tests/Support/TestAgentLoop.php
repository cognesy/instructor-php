<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;
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
        int $maxIterations = 1,
    ) {
        parent::__construct(
            tools: $tools,
            toolExecutor: $toolExecutor,
            driver: $driver,
            events: $events,
            interceptor: $interceptor,
        );
        $this->maxIterations = $maxIterations;
    }

    #[\Override]
    protected function shouldStop(AgentState $state): bool
    {
        return $state->stepCount() >= $this->maxIterations;
    }
}

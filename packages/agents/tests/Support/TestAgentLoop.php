<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support;

use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Agents\Hooks\Interceptors\CanInterceptAgentLifecycle;

final class TestAgentLoop extends AgentLoop
{
    private int $maxIterations;

    public function __construct(
        Tools $tools,
        CanExecuteToolCalls $toolExecutor,
        CanUseTools $driver,
        CanEmitAgentEvents $eventEmitter,
        ?CanInterceptAgentLifecycle $interceptor = null,
        int $maxIterations = 1,
    ) {
        parent::__construct(
            tools: $tools,
            toolExecutor: $toolExecutor,
            driver: $driver,
            eventEmitter: $eventEmitter,
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

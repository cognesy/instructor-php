<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support;

use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Agents\Lifecycle\CanObserveAgentLifecycle;
use tmp\ErrorHandling\Contracts\CanHandleAgentErrors;

final class TestAgentLoop extends AgentLoop
{
    private int $maxIterations;

    public function __construct(
        Tools $tools,
        CanExecuteToolCalls $toolExecutor,
        CanHandleAgentErrors $errorHandler,
        CanUseTools $driver,
        CanEmitAgentEvents $eventEmitter,
        ?CanObserveAgentLifecycle $observer = null,
        int $maxIterations = 1,
    ) {
        parent::__construct(
            tools: $tools,
            toolExecutor: $toolExecutor,
            errorHandler: $errorHandler,
            driver: $driver,
            eventEmitter: $eventEmitter,
            observer: $observer,
        );
        $this->maxIterations = $maxIterations;
    }

    #[\Override]
    protected function shouldStop(AgentState $state): bool
    {
        return $state->stepCount() >= $this->maxIterations;
    }
}

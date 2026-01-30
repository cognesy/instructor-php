<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanControlAgentLoop;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Zero\Data\AgentState;
use Cognesy\Agents\Zero\Data\AgentStep;
use Cognesy\Agents\Zero\Data\AgentLoopStage;
use Cognesy\Agents\Zero\Hooks\HookStack;
use Cognesy\Agents\Zero\Hooks\HookType;
use Cognesy\Agents\Zero\Stop\StopReason;
use Throwable;

final class AgentLoop implements CanControlAgentLoop
{
    private EventEmitter $emitter;

    public function __construct(
        private readonly Tools $tools,
        private readonly CanUseTools $driver,
        private readonly CanExecuteToolCalls $toolExecutor,
        private readonly HookStack $hooks,
    ) {
        $this->emitter = new EventEmitter();
    }

    #[\Override]
    public function execute(AgentState $state): AgentState {
        $final = $state;
        foreach ($this->iterate($state) as $stepState) {
            $final = $stepState;
        }
        return $final;
    }

    #[\Override]
    public function iterate(AgentState $state): iterable {
        $state = $this->on(AgentLoopStage::BeforeExecution, $state);

        while (true) {
            $state = $this->on(AgentLoopStage::BeforeStep, $state);
            if ($this->shouldStop($state)) {
                break;
            }

            $state = $this->on(AgentLoopStage::BeforeToolCall, $state);
            if ($this->shouldStop($state)) {
                break;
            }

            $step = $this->driver->useTools($state, $this->tools);
            $state = $state->withAddedStep($step);

            if (!$state->hasToolCalls()) {
                break;
            }
        }

        $state = $this->on(AgentLoopStage::AfterExecution, $state);
        yield $state;
    }

    public function on(AgentLoopStage $stage, AgentState $state): AgentState {
        $state = $this->hooks->on($stage->toHookType(), $state);
        $this->emitter->emitEvent($stage, $state);
        return $state;
    }

    private function shouldStop(AgentState $state): bool {
    }
}

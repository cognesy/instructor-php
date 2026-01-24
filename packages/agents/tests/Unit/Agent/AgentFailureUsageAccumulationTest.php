<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Agent;
use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\Agent\Continuation\ContinuationCriteria;
use Cognesy\Agents\Agent\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Agent\Contracts\CanUseTools;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\ErrorHandling\AgentErrorHandler;
use Cognesy\Agents\Agent\Events\AgentEventEmitter;
use Cognesy\Agents\Agent\ToolExecutor;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\Usage;

final class CountingUsage extends Usage
{
    public function withAccumulated(Usage $usage): Usage {
        return new self(
            inputTokens: $this->inputTokens + 1,
            outputTokens: $this->outputTokens,
            cacheWriteTokens: $this->cacheWriteTokens,
            cacheReadTokens: $this->cacheReadTokens,
            reasoningTokens: $this->reasoningTokens,
        );
    }
}

describe('Agent failure usage accumulation', function () {
    it('accumulates usage when error handler records a step result', function () {
        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
                throw new \RuntimeException('driver boom');
            }
        };

        $tools = new Tools();
        $agent = new Agent(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools),
            errorHandler: AgentErrorHandler::default(),
            processors: null,
            continuationCriteria: new ContinuationCriteria(),
            driver: $driver,
            eventEmitter: new AgentEventEmitter(),
        );

        $state = AgentState::empty()
            ->withMessages(Messages::fromString('ping'))
            ->withUsage(new CountingUsage());

        // Use iterate() to get the first (failed) state
        $failedState = null;
        foreach ($agent->iterate($state) as $stepState) {
            $failedState = $stepState;
            break; // Just get the first state (the failure)
        }

        expect($failedState)->not->toBeNull();
        expect($failedState->usage()->input())->toBe(1);
    });
});

<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\Agent\Core\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\Agent\Core\ToolExecutor;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
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
    it('accumulates usage when onFailure records a step result', function () {
        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
                throw new \RuntimeException('driver boom');
            }
        };

        $tools = new Tools();
        $agent = new Agent(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools),
            processors: new StateProcessors(),
            continuationCriteria: new ContinuationCriteria(),
            driver: $driver,
            events: null,
        );

        $state = AgentState::empty()
            ->withMessages(Messages::fromString('ping'))
            ->withUsage(new CountingUsage());
        $failedState = $agent->nextStep($state);

        expect($failedState->usage()->input())->toBe(1);
    });
});

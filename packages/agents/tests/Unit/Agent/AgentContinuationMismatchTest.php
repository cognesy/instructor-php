<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Agent;
use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\Agent\Continuation\ContinuationCriteria;
use Cognesy\Agents\Agent\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Agent\Contracts\CanUseTools;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\StateProcessing\StateProcessors;
use Cognesy\Agents\Agent\ToolExecutor;

it('throws when steps exist without matching step results', function () {
    $driver = new class implements CanUseTools {
        public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
            return new AgentStep();
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

    $state = AgentState::empty()->recordStep(new AgentStep());

    expect(fn() => $agent->hasNextStep($state))->toThrow(\LogicException::class);
});

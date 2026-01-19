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

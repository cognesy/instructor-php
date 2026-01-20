<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\Agent\Core\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\Agent\Drivers\Testing\ScenarioStep;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\Agent\Core\Enums\AgentStatus;
use Cognesy\Addons\Agent\Core\ToolExecutor;
use Cognesy\Addons\Agent\Tools\Testing\MockTool;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Messages\Messages;

describe('Deterministic agent execution', function () {
    it('runs a trivial scenario without tools or LLM', function () {
        $agent = AgentBuilder::base()
            ->withDriver(DeterministicAgentDriver::fromResponses('Paris'))
            ->build();

        $state = AgentState::empty()
            ->withMessages(Messages::fromString('What is the capital of France?'));

        $final = $agent->finalStep($state);

        expect($final->stepCount())->toBe(1);
        expect($final->currentStep()?->hasToolCalls())->toBeFalse();
        expect($final->messages()->toString())->toContain('Paris');
    });

    it('executes mock tools via deterministic tool-call scenario', function () {
        $toolCalls = [];
        $tool = new MockTool(
            name: 'get_capital',
            description: 'Returns a capital for a country',
            handler: function (string $country) use (&$toolCalls): string {
                $toolCalls[] = $country;
                return 'Paris';
            },
        );

        $driver = DeterministicAgentDriver::fromSteps(
            ScenarioStep::toolCall('get_capital', ['country' => 'France'], response: 'using tool'),
            ScenarioStep::final('Paris'),
        );

        $agent = AgentBuilder::base()
            ->withTools([$tool])
            ->withDriver($driver)
            ->addContinuationCriteria(
                ContinuationCriteria::when(
                    static fn(AgentState $state): ContinuationDecision => match (true) {
                        $state->stepCount() < 2 => ContinuationDecision::RequestContinuation,
                        default => ContinuationDecision::AllowStop,
                    }
                )
            )
            ->build();

        $state = AgentState::empty()
            ->withMessages(Messages::fromString('What is the capital of France?'));

        $final = $agent->finalStep($state);

        expect($final->stepCount())->toBe(2);
        expect($toolCalls)->toBe(['France']);

        $executions = $final->stepAt(0)?->toolExecutions()->all() ?? [];
        expect($executions)->toHaveCount(1);
        expect($executions[0]->value())->toBe('Paris');
    });

    it('stops after a driver failure when continuation outcome is recorded', function () {
        $driver = new class implements CanUseTools {
            private int $calls = 0;

            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
                $this->calls++;
                if ($this->calls === 1) {
                    return new AgentStep();
                }
                throw new \RuntimeException('driver boom');
            }
        };

        $criterion = ContinuationCriteria::when(
            static fn(AgentState $state): ContinuationDecision => match (true) {
                $state->stepCount() < 2 => ContinuationDecision::RequestContinuation,
                default => ContinuationDecision::AllowStop,
            }
        );
        $continuationCriteria = new ContinuationCriteria($criterion);

        $tools = new Tools();
        $agent = new Agent(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools),
            processors: new StateProcessors(),
            continuationCriteria: $continuationCriteria,
            driver: $driver,
            events: null,
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('ping'));
        $state = $agent->nextStep($state);
        $failedState = $agent->nextStep($state);

        expect($failedState->status())->toBe(AgentStatus::Failed);
        expect($agent->hasNextStep($failedState))->toBeFalse();
    });
});

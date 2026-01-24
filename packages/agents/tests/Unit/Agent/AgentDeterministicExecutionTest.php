<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Agent;
use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\Agent\Continuation\ContinuationCriteria;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Agent\Contracts\CanUseTools;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Agent\Enums\AgentStatus;
use Cognesy\Agents\Agent\ErrorHandling\AgentErrorHandler;
use Cognesy\Agents\Agent\Events\AgentEventEmitter;
use Cognesy\Agents\Agent\ToolExecutor;
use Cognesy\Agents\Agent\Tools\MockTool;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Messages\Messages;

describe('Deterministic agent execution', function () {
    it('runs a trivial scenario without tools or LLM', function () {
        $agent = AgentBuilder::base()
            ->withDriver(DeterministicAgentDriver::fromResponses('Paris'))
            ->build();

        $state = AgentState::empty()
            ->withMessages(Messages::fromString('What is the capital of France?'));

        $final = $agent->execute($state);

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

        // Note: Criteria are evaluated AFTER step completes but BEFORE it's recorded to stepResults.
        // So step counting must include currentStep to reflect the step being evaluated.
        $agent = AgentBuilder::base()
            ->withTools([$tool])
            ->withDriver($driver)
            ->addContinuationCriteria(
                ContinuationCriteria::when(
                    static fn(AgentState $state): ContinuationDecision => match (true) {
                        ($state->stepCount() + ($state->currentStep() !== null ? 1 : 0)) < 2 => ContinuationDecision::RequestContinuation,
                        default => ContinuationDecision::AllowStop,
                    }
                )
            )
            ->build();

        $state = AgentState::empty()
            ->withMessages(Messages::fromString('What is the capital of France?'));

        $final = $agent->execute($state);

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

        // Note: Criteria are evaluated AFTER step completes but BEFORE it's recorded to stepResults.
        // So step counting must include currentStep to reflect the step being evaluated.
        $criterion = ContinuationCriteria::when(
            static fn(AgentState $state): ContinuationDecision => match (true) {
                ($state->stepCount() + ($state->currentStep() !== null ? 1 : 0)) < 2 => ContinuationDecision::RequestContinuation,
                default => ContinuationDecision::AllowStop,
            }
        );
        $continuationCriteria = new ContinuationCriteria($criterion);

        $tools = new Tools();
        $agent = new Agent(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools),
            errorHandler: AgentErrorHandler::default(),
            processors: null,
            continuationCriteria: $continuationCriteria,
            driver: $driver,
            eventEmitter: new AgentEventEmitter(),
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('ping'));

        // Use iterate() to step through and observe failure
        $states = [];
        foreach ($agent->iterate($state) as $stepState) {
            $states[] = $stepState;
        }

        // First step succeeds, second step fails
        expect($states)->toHaveCount(2);
        expect($states[1]->status())->toBe(AgentStatus::Failed);
    });
});

<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Agent\Agent;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\ErrorHandling\AgentErrorHandler;
use Cognesy\Agents\Core\Events\AgentEventEmitter;
use Cognesy\Agents\Core\Tools\MockTool;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
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

        // Note: Criteria are evaluated AFTER step completes but BEFORE it's recorded to stepExecutions.
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

        // Note: Criteria are evaluated AFTER step completes but BEFORE it's recorded to stepExecutions.
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

<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Hooks\CallableHook;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\AgentStatus;
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

        // Evaluate after each step to request exactly two steps.
        $continuationHook = new CallableHook(
            events: [HookType::AfterStep],
            callback: static function (AgentState $state, HookType $event): AgentState {
                $decision = match (true) {
                    $state->transientStepCount() < 2 => ContinuationDecision::RequestContinuation,
                    default => ContinuationDecision::AllowStop,
                };

                return $state->withEvaluation(
                    ContinuationEvaluation::fromDecision(CallableHook::class, $decision)
                );
            },
        );

        $agent = AgentBuilder::base()
            ->withTools([$tool])
            ->withDriver($driver)
            ->addHook($continuationHook, -200)
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

        $agentLoop = AgentBuilder::base()
            ->withDriver($driver)
            ->addHook(new CallableHook(
                events: [HookType::AfterStep],
                callback: static function (AgentState $state, HookType $event): AgentState {
                    $decision = match (true) {
                        $state->transientStepCount() < 2 => ContinuationDecision::RequestContinuation,
                        default => ContinuationDecision::AllowStop,
                    };

                    return $state->withEvaluation(
                        ContinuationEvaluation::fromDecision(CallableHook::class, $decision)
                    );
                },
            ), -200)
            ->build();

        $state = AgentState::empty()->withMessages(Messages::fromString('ping'));

        // Use iterate() to step through and observe failure
        $states = [];
        foreach ($agentLoop->iterate($state) as $stepState) {
            $states[] = $stepState;
        }

        // First step succeeds, second step fails
        expect($states)->toHaveCount(2);
        expect($states[1]->status())->toBe(AgentStatus::Failed);
    });
});

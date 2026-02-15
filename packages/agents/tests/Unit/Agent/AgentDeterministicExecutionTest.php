<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseHook;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Hooks\CallableHook;
use Cognesy\Agents\Tool\Tools\MockTool;
use Cognesy\Messages\Messages;

describe('Deterministic agent execution', function () {
    it('runs a trivial scenario without tools or LLM', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('Paris')))
            ->build();

        $state = AgentState::empty()
            ->withMessages(Messages::fromString('What is the capital of France?'));

        $final = $agent->execute($state);

        expect($final->stepCount())->toBe(1);
        expect($final->currentStepOrLast()?->hasToolCalls())->toBeFalse();
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

        $driver = FakeAgentDriver::fromSteps(
            ScenarioStep::toolCall('get_capital', ['country' => 'France'], response: 'using tool'),
            ScenarioStep::final('Paris'),
        );

        // Evaluate after each step to request exactly two steps.
        $continuationHook = new CallableHook(
            static function (HookContext $context): HookContext {
                $state = $context->state();
                if ($state->stepCount() < 1) {
                    return $context->withState($state->withExecutionContinued());
                }
                return $context;
            }
        );

        $agent = AgentBuilder::base()
            ->withCapability(new UseTools($tool))
            ->withCapability(new UseDriver($driver))
            ->withCapability(new UseHook($continuationHook, HookTriggers::afterStep(), -200))
            ->build();

        $state = AgentState::empty()
            ->withMessages(Messages::fromString('What is the capital of France?'));

        $final = $agent->execute($state);

        expect($final->stepCount())->toBe(2);
        expect($toolCalls)->toBe(['France']);

        $executions = $final->steps()->stepAt(0)?->toolExecutions()->all() ?? [];
        expect($executions)->toHaveCount(1);
        expect($executions[0]->value())->toBe('Paris');
    });

    it('stops after a driver failure when failure is recorded', function () {
        $driver = new FakeAgentDriver([
            ScenarioStep::toolCall('check', ['q' => 'test'], executeTools: false),
            ScenarioStep::error('boom'),
        ]);

        $agentLoop = AgentBuilder::base()
            ->withCapability(new UseDriver($driver))
            ->build();

        $state = AgentState::empty()->withMessages(Messages::fromString('ping'));

        // Use iterate() to step through and observe failure
        $states = [];
        foreach ($agentLoop->iterate($state) as $stepState) {
            $states[] = $stepState;
        }

        // First step has tool calls → continues, second step has errors → stops as failed
        expect($states)->toHaveCount(2);
        expect($states[1]->status())->toBe(ExecutionStatus::Failed);
    });
});

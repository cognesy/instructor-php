<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Hooks\CallableHook;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Messages\Messages;

it('increments step numbers across iterations', function () {
    $driver = new class implements CanUseTools {
        public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
            return new AgentStep();
        }
    };

    $agent = AgentBuilder::base()
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
    $final = $agent->execute($state);

    $executions = $final->stepExecutions()->all();

    expect($executions)->toHaveCount(2);
    expect($executions[0]->stepNumber)->toBe(1);
    expect($executions[1]->stepNumber)->toBe(2);
});

<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Hooks\CallableHook;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookTriggers;
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
        ->addHook(
            new CallableHook(
                static function (HookContext $context): HookContext {
                    $state = $context->state();
                    if ($state->stepCount() < 1) {
                        return $context->withState($state->withExecutionContinued());
                    }
                    return $context;
                }
            ),
            HookTriggers::afterStep(),
            -200
        )
        ->build();

    $state = AgentState::empty()->withMessages(Messages::fromString('ping'));
    $final = $agent->execute($state);

    $executions = $final->stepExecutions()->all();

    expect($executions)->toHaveCount(2);
    expect($executions[0]->stepNumber)->toBe(1);
    expect($executions[1]->stepNumber)->toBe(2);
})->skip('hooks not integrated yet');

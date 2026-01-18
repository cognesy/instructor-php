<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\Agent\Core\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Messages\Messages;

final class SlowDriver implements CanUseTools
{
    public function __construct(private DeterministicAgentDriver $driver) {}

    #[\Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep
    {
        usleep(2000);
        return $this->driver->useTools($state, $tools, $executor);
    }
}

it('tracks cumulative execution time across steps', function () {
    $driver = new SlowDriver(DeterministicAgentDriver::fromResponses('one', 'two'));
    $criteria = ContinuationCriteria::when(
        static fn(AgentState $state): ContinuationDecision => match (true) {
            $state->stepCount() < 2 => ContinuationDecision::RequestContinuation,
            default => ContinuationDecision::AllowStop,
        }
    );

    $agent = AgentBuilder::base()
        ->withDriver($driver)
        ->addContinuationCriteria($criteria)
        ->build();

    $state = AgentState::empty()->withMessages(Messages::fromString('hi'));
    $finalState = $agent->finalStep($state);

    expect($finalState->stepCount())->toBe(2);
    expect($finalState->stateInfo()->cumulativeExecutionSeconds())->toBeGreaterThanOrEqual(0.002);
});

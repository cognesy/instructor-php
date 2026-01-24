<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\Agent\Continuation\ContinuationCriteria;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Agent\Contracts\CanUseTools;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;
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

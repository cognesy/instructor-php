<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Messages\Messages;
use DateTimeImmutable;

it('records step start time before driver work completes', function () {
    $driver = new class implements CanUseTools {
        public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
            usleep(500_000);
            return new AgentStep();
        }
    };

    $agent = AgentBuilder::base()
        ->withDriver($driver)
        ->build();

    $state = AgentState::empty()->withMessages(Messages::fromString('ping'));

    $startedBefore = new DateTimeImmutable();
    $finalState = $agent->execute($state);
    $result = $finalState->lastStepExecution();

    expect($result)->not->toBeNull();

    $startedAt = $result->startedAt;
    $completedAt = $result->completedAt;

    $startedBeforeFloat = (float) $startedBefore->format('U.u');
    $startedAtFloat = (float) $startedAt->format('U.u');
    $completedAtFloat = (float) $completedAt->format('U.u');

    expect($startedAtFloat)->toBeGreaterThanOrEqual($startedBeforeFloat)
        ->and($completedAtFloat)->toBeGreaterThanOrEqual($startedAtFloat)
        ->and($startedAtFloat - $startedBeforeFloat)->toBeLessThan(0.45);
});

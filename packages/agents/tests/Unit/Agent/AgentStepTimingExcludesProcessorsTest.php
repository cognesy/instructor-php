<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Agent\Agent;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Continuation\Criteria\StepsLimit;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\ErrorHandling\AgentErrorHandler;
use Cognesy\Agents\Core\Events\AgentEventEmitter;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Agents\Agent\StateProcessing\StateProcessors;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Messages\Messages;

it('records step start time after processor work completes', function () {
    $sleptUntil = 0.0;

    $processor = new class($sleptUntil) implements CanProcessAgentState {
        /** @var float */
        private $sleptUntilRef;

        public function __construct(float &$sleptUntil) {
            $this->sleptUntilRef = &$sleptUntil;
        }

        public function canProcess(AgentState $state): bool {
            return true;
        }

        public function process(AgentState $state, ?callable $next = null): AgentState {
            usleep(200_000);
            $this->sleptUntilRef = microtime(true);
            return $next ? $next($state) : $state;
        }
    };

    $driver = new class implements CanUseTools {
        public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
            return new AgentStep(inputMessages: $state->messages());
        }
    };

    $tools = new Tools();
    $agent = new Agent(
        tools: $tools,
        toolExecutor: new ToolExecutor($tools),
        errorHandler: AgentErrorHandler::default(),
        processors: new StateProcessors($processor),
        continuationCriteria: new ContinuationCriteria(
            new StepsLimit(1, static fn(AgentState $state): int => $state->transientStepCount()),
        ),
        driver: $driver,
        eventEmitter: new AgentEventEmitter(),
    );

    $state = AgentState::empty()->withMessages(Messages::fromString('ping'));
    $finalState = $agent->execute($state);

    $execution = $finalState->lastStepExecution();
    expect($execution)->not->toBeNull();

    $startedAt = (float) $execution->startedAt->format('U.u');
    expect($startedAt)->toBeGreaterThan($sleptUntil);
});

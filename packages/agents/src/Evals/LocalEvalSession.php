<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\CanControlAgentLoop;
use Cognesy\Agents\Data\AgentState;
use Override;

final class LocalEvalSession implements CanUseAgentEvalSession
{
    private AgentState $state;
    private AgentRun $run;
    private EvalTracePolicy $policy;

    /** @var list<object> */
    private array $pendingEvents = [];

    public function __construct(
        private readonly CanControlAgentLoop $loop,
        ?EvalTracePolicy $policy = null,
    ) {
        $this->state = AgentState::empty();
        $this->run = AgentRun::empty();
        $this->policy = $policy ?? EvalTracePolicy::safe();
        if ($loop instanceof AgentLoop) {
            $loop->wiretap(function (object $event): void {
                $this->pendingEvents[] = $event;
            });
        }
    }

    #[Override]
    public function send(string $message): EvalTurn {
        $this->pendingEvents = [];
        $this->state = $this->loop->execute($this->state->withUserMessage($message));
        $this->run = AgentRun::fromState($this->state, $this->pendingEvents, $this->run, $this->policy);
        return new EvalTurn($this->run->turns(), $message, $this->run);
    }

    #[Override]
    public function run(): AgentRun {
        return $this->run;
    }
}

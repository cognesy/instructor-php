<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Override;

final class HttpEvalSession implements CanUseAgentEvalSession
{
    private AgentRun $run;

    public function __construct(
        private readonly HttpAgentTarget $target,
        private readonly string $sessionId,
        ?AgentRun $run = null,
    ) {
        $this->run = $run ?? AgentRun::empty();
    }

    #[Override]
    public function send(string $message): EvalTurn {
        $this->run = HttpAgentTarget::runFromPayload($this->target->sendTurn($this->sessionId, $message), $this->target->policy());
        return new EvalTurn($this->run->turns(), $message, $this->run);
    }

    #[Override]
    public function run(): AgentRun {
        return $this->run;
    }

    public function sessionId(): string {
        return $this->sessionId;
    }
}

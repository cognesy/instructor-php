<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

interface CanUseAgentEvalSession
{
    public function send(string $message): EvalTurn;

    public function run(): AgentRun;
}

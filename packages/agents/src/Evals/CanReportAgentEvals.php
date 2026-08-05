<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

interface CanReportAgentEvals
{
    public function id(): string;

    public function onRunStarted(int $caseCount): void;

    public function onEvalCompleted(EvalResult $result): void;

    public function onRunCompleted(EvalRunResult $result): void;
}

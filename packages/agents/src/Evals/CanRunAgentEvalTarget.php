<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

interface CanRunAgentEvalTarget
{
    public function open(?EvalSessionRequest $request = null): CanUseAgentEvalSession;
}

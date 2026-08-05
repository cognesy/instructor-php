<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

interface CanJudgeAgentEval
{
    public function judge(JudgeRequest $request): JudgeScore;
}

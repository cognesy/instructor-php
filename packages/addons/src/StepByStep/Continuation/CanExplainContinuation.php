<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

interface CanExplainContinuation
{
    public function explain(object $state): ContinuationEvaluation;
}

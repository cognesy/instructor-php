<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

interface CanProvideStopReason
{
    public function stopReason(object $state, ContinuationDecision $decision): ?StopReason;
}

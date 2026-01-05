<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

/**
 * Generic continuation criteria contract.
 *
 * @template TState of object
 */
interface CanDecideToContinue
{
    /**
     * Decide whether to continue, stop, or forbid continuation.
     *
     * @param TState $state
     * @return ContinuationDecision The criterion's decision
     */
    public function decide(object $state): ContinuationDecision;
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation\Contracts;

use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Unified continuation criterion contract.
 *
 * Each criterion returns a complete ContinuationEvaluation containing:
 *   - decision (ForbidContinuation, AllowContinuation, RequestContinuation, AllowStop)
 *   - reason (human-readable explanation)
 *   - stopReason (optional, for when stopping)
 *   - context (optional, additional data)
 */
interface CanEvaluateContinuation
{
    /**
     * Evaluate the state and return a complete evaluation.
     */
    public function evaluate(AgentState $state): ContinuationEvaluation;
}

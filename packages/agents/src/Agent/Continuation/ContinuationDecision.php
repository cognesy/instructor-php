<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation;

/**
 * Represents a criterion's decision about whether to continue execution.
 *
 * Two categories of criteria:
 *   - Guards (limits, error checks): ForbidContinuation / AllowContinuation
 *   - Work drivers (tool calls, self-critic): RequestContinuation / AllowStop
 *
 * Resolution logic (order-independent):
 *   1. Any ForbidContinuation → STOP (guard denied)
 *   2. Any RequestContinuation → CONTINUE (work requested, overrides AllowStop)
 *   3. Any AllowStop → STOP (work driver finished)
 *   4. Any AllowContinuation (no Stop) → CONTINUE (guards permit, bootstrap)
 */
enum ContinuationDecision: string
{
    // === Guards use these ===

    /**
     * Guard denial - execution must stop immediately.
     * Use for: limits exceeded, errors present, fatal conditions.
     * Takes priority over all other decisions.
     */
    case ForbidContinuation = 'forbid';

    /**
     * Guard approval - this guard permits continuation.
     * Use for: under limit, no errors, conditions acceptable.
     * Does not drive continuation - just permits it.
     */
    case AllowContinuation = 'allow';

    // === Work drivers use these ===

    /**
     * Work request - this criterion has pending work and wants to continue.
     * Use for: tool calls present, revision requested, subtasks pending.
     * Drives continuation when no guard forbids.
     */
    case RequestContinuation = 'request';

    /**
     * Work complete - this criterion has no more work.
     * Use for: no tool calls present, task completed from this criterion's perspective.
     * Does not drive continuation.
     */
    case AllowStop = 'stop';

    /**
     * Resolve multiple decisions into a single boolean.
     *
     * Resolution priority:
     *   1. Any ForbidContinuation → false (guard denied)
     *   2. Any RequestContinuation → true (work requested, overrides AllowStop)
     *   3. Any AllowStop → false (work driver finished)
     *   4. Any AllowContinuation (no Stop) → true (guards permit, bootstrap)
     *   5. No decisions → false
     *
     * @param ContinuationDecision ...$decisions
     */
    public static function canContinueWith(self ...$decisions): bool {
        $hasForbid = false;
        $hasRequest = false;
        $hasAllow = false;
        $hasStop = false;

        foreach ($decisions as $decision) {
            match ($decision) {
                self::ForbidContinuation => $hasForbid = true,
                self::RequestContinuation => $hasRequest = true,
                self::AllowContinuation => $hasAllow = true,
                self::AllowStop => $hasStop = true,
            };

            // Early exit on forbid
            if ($hasForbid) {
                return false;
            }
        }

        // Request wins (overrides Stop), else Allow wins only if no Stop (bootstrap)
        return $hasRequest || ($hasAllow && !$hasStop);
    }
}

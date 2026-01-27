<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation;

use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;

/**
 * Static methods to process continuation evaluations.
 *
 * Encapsulates the priority-based resolution logic used by ContinuationCriteria
 * and ContinuationOutcome.
 */
final class EvaluationProcessor
{
    /**
     * Determine whether to continue based on evaluations.
     *
     * Resolution priority:
     *   1. Any ForbidContinuation → false (guard denied)
     *   2. Any RequestContinuation → true (work requested, overrides AllowStop)
     *   3. Any AllowStop → false (work driver finished)
     *   4. Any AllowContinuation (no Stop) → true (guards permit, bootstrap)
     *   5. No decisions → false
     *
     * @param list<ContinuationEvaluation> $evaluations
     */
    public static function shouldContinue(array $evaluations): bool {
        $decisions = array_map(
            static fn(ContinuationEvaluation $eval): ContinuationDecision => $eval->decision,
            $evaluations,
        );

        return ContinuationDecision::canContinueWith(...$decisions);
    }

    /**
     * Find the criterion class that resolved the decision.
     *
     * For stop: finds the first ForbidContinuation criterion.
     * For continue: finds the first RequestContinuation criterion.
     * Returns 'aggregate' if no single criterion was decisive.
     *
     * @param list<ContinuationEvaluation> $evaluations
     * @return class-string|'aggregate'
     */
    public static function findResolver(array $evaluations, bool $shouldContinue): string {
        if (!$shouldContinue) {
            // Find first forbidding criterion
            foreach ($evaluations as $eval) {
                if ($eval->decision === ContinuationDecision::ForbidContinuation) {
                    return $eval->criterionClass;
                }
            }
        } else {
            // Find first requesting criterion
            foreach ($evaluations as $eval) {
                if ($eval->decision === ContinuationDecision::RequestContinuation) {
                    return $eval->criterionClass;
                }
            }
        }

        return 'aggregate';
    }

    /**
     * Determine the stop reason from evaluations.
     *
     * If continuing, returns Completed.
     * If stopping, uses the stop reason from the forbidding criterion,
     * or falls back to GuardForbade if none provided.
     *
     * @param list<ContinuationEvaluation> $evaluations
     */
    public static function determineStopReason(array $evaluations, bool $shouldContinue): StopReason {
        if ($shouldContinue) {
            return StopReason::Completed;
        }

        // Find stop reason from first forbidding criterion
        foreach ($evaluations as $eval) {
            if ($eval->decision === ContinuationDecision::ForbidContinuation) {
                return $eval->stopReason ?? StopReason::GuardForbade;
            }
        }

        // No forbid found, but we're stopping - work completed
        return StopReason::Completed;
    }

    /**
     * Determine the aggregate decision from evaluations.
     *
     * @param list<ContinuationEvaluation> $evaluations
     */
    public static function determineDecision(array $evaluations, bool $shouldContinue): ContinuationDecision {
        if ($shouldContinue) {
            return ContinuationDecision::RequestContinuation;
        }

        // Check if there's a forbid
        foreach ($evaluations as $eval) {
            if ($eval->decision === ContinuationDecision::ForbidContinuation) {
                return ContinuationDecision::ForbidContinuation;
            }
        }

        return ContinuationDecision::AllowStop;
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation\Criteria;

use BackedEnum;
use Closure;
use Cognesy\Agents\Agent\Continuation\CanEvaluateContinuation;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Continuation\ContinuationEvaluation;
use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Guard: Forbids continuation when the current step's finish reason matches a configured set.
 *
 * Returns ForbidContinuation when finish reason matches stop reasons (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 */
final readonly class FinishReasonCheck implements CanEvaluateContinuation
{
    /** @var Closure(AgentState): mixed */
    private Closure $finishReasonResolver;
    /** @var list<string> */
    private array $normalizedStopReasons;

    /**
     * @param array<int, string|int|BackedEnum|null> $stopReasons
     * @param callable(AgentState): (string|int|BackedEnum|null) $finishReasonResolver
     */
    public function __construct(
        array $stopReasons,
        callable $finishReasonResolver,
    ) {
        $this->finishReasonResolver = Closure::fromCallable($finishReasonResolver);
        $this->normalizedStopReasons = $this->normalizeStopReasons($stopReasons);
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
        if ($this->normalizedStopReasons === []) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowContinuation,
                reason: 'No stop reasons configured',
                context: ['finishReason' => null, 'stopReasons' => []],
            );
        }

        $reason = ($this->finishReasonResolver)($state);
        $originalReason = $reason;
        if ($reason instanceof BackedEnum) {
            $reason = $reason->value;
        }
        if ($reason === null) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowContinuation,
                reason: 'No finish reason available',
                context: ['finishReason' => null, 'stopReasons' => $this->normalizedStopReasons],
            );
        }

        $shouldStop = in_array($reason, $this->normalizedStopReasons, true);
        $decision = $shouldStop
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;

        $reasonText = $shouldStop
            ? sprintf('Finish reason "%s" matched stop condition', (string) $reason)
            : sprintf('Finish reason "%s" does not match stop conditions', (string) $reason);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reasonText,
            context: [
                'finishReason' => $originalReason instanceof BackedEnum ? $originalReason->value : $originalReason,
                'stopReasons' => $this->normalizedStopReasons,
            ],
            stopReason: $shouldStop ? StopReason::FinishReasonReceived : null,
        );
    }

    /**
     * @param array<int, string|int|BackedEnum|null> $stopReasons
     * @return list<string>
     */
    private function normalizeStopReasons(array $stopReasons): array {
        $normalized = [];
        foreach ($stopReasons as $reason) {
            if ($reason instanceof BackedEnum) {
                $normalized[] = (string) $reason->value;
                continue;
            }
            if ($reason === null) {
                continue;
            }
            $normalized[] = (string) $reason;
        }

        return $normalized;
    }
}

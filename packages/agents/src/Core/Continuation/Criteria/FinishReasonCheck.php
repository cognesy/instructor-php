<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation\Criteria;

use Closure;
use Cognesy\Agents\Core\Continuation\Contracts\CanEvaluateContinuation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

/**
 * Guard: Forbids continuation when the current step's finish reason matches a configured set.
 *
 * Returns ForbidContinuation when finish reason matches stop reasons (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 */
final readonly class FinishReasonCheck implements CanEvaluateContinuation
{
    /** @var Closure(AgentState): ?InferenceFinishReason */
    private Closure $finishReasonResolver;
    /** @var list<InferenceFinishReason> */
    private array $stopReasons;

    /**
     * @param array<int, InferenceFinishReason> $stopReasons
     * @param callable(AgentState): ?InferenceFinishReason $finishReasonResolver
     */
    public function __construct(
        array $stopReasons,
        callable $finishReasonResolver,
    ) {
        $this->finishReasonResolver = Closure::fromCallable($finishReasonResolver);
        $this->stopReasons = array_values($stopReasons);
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
        if ($this->stopReasons === []) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowContinuation,
                reason: 'No stop reasons configured',
                context: ['finishReason' => null, 'stopReasons' => []],
            );
        }

        $reason = ($this->finishReasonResolver)($state);
        if ($reason === null) {
            return new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::AllowContinuation,
                reason: 'No finish reason available',
                context: ['finishReason' => null, 'stopReasons' => $this->stopReasonsAsStrings()],
            );
        }

        $shouldStop = in_array($reason, $this->stopReasons, true);
        $decision = $shouldStop
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;

        $reasonText = $shouldStop
            ? sprintf('Finish reason "%s" matched stop condition', $reason->value)
            : sprintf('Finish reason "%s" does not match stop conditions', $reason->value);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reasonText,
            context: [
                'finishReason' => $reason->value,
                'stopReasons' => $this->stopReasonsAsStrings(),
            ],
            stopReason: $shouldStop ? StopReason::FinishReasonReceived : null,
        );
    }

    /**
     * @return list<string>
     */
    private function stopReasonsAsStrings(): array {
        return array_map(
            static fn(InferenceFinishReason $reason): string => $reason->value,
            $this->stopReasons,
        );
    }
}

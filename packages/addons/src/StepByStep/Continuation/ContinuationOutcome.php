<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

final readonly class ContinuationOutcome
{
    /**
     * @param list<ContinuationEvaluation> $evaluations
     */
    public function __construct(
        public ContinuationDecision $decision,
        public bool $shouldContinue,
        public string $resolvedBy,
        public StopReason $stopReason,
        public array $evaluations,
    ) {}

    public function shouldContinue(): bool {
        return $this->shouldContinue;
    }

    public function getEvaluationFor(string $criterionClass): ?ContinuationEvaluation {
        foreach ($this->evaluations as $eval) {
            if ($eval->criterionClass === $criterionClass) {
                return $eval;
            }
        }
        return null;
    }

    public function getForbiddingCriterion(): ?string {
        foreach ($this->evaluations as $eval) {
            if ($eval->decision === ContinuationDecision::ForbidContinuation) {
                return $eval->criterionClass;
            }
        }
        return null;
    }

    public function toArray(): array {
        return [
            'decision' => $this->decision->value,
            'shouldContinue' => $this->shouldContinue,
            'resolvedBy' => $this->resolvedBy,
            'stopReason' => $this->stopReason->value,
            'evaluations' => array_map(
                static fn(ContinuationEvaluation $evaluation): array => [
                    'criterion' => $evaluation->criterionClass,
                    'decision' => $evaluation->decision->value,
                    'reason' => $evaluation->reason,
                    'stopReason' => $evaluation->stopReason?->value,
                    'context' => $evaluation->context,
                ],
                $this->evaluations,
            ),
        ];
    }
}

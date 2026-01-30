<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation\Data;

use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Continuation\EvaluationProcessor;
use Throwable;

/**
 * Immutable outcome of continuation evaluation.
 *
 * Core data:
 *   - shouldContinue: boolean result
 *   - evaluations: list of individual evaluations
 *
 * Derived data (computed from evaluations):
 *   - decision(): aggregate ContinuationDecision
 *   - resolvedBy(): the evaluation source that made the decision
 *   - stopReason(): reason for stopping (if applicable)
 */
final readonly class ContinuationOutcome
{
    /**
     * @param list<ContinuationEvaluation> $evaluations
     */
    public function __construct(
        public bool $shouldContinue,
        public array $evaluations,
    ) {}

    /**
     * Create an outcome from evaluations using the processor.
     *
     * @param list<ContinuationEvaluation> $evaluations
     */
    public static function fromEvaluations(array $evaluations): self {
        return new self(
            shouldContinue: EvaluationProcessor::shouldContinue($evaluations),
            evaluations: $evaluations,
        );
    }

    /**
     * Create an empty outcome (no evaluations).
     * With no stopping evaluations defined, continue by default.
     */
    public static function empty(): self {
        return new self(
            shouldContinue: true,
            evaluations: [],
        );
    }

    /**
     * Create an outcome when continuation evaluation fails.
     */
    public static function fromEvaluationError(Throwable $error): self {
        $message = $error->getMessage();
        if ($message === '') {
            $message = $error::class;
        }

        $evaluation = new ContinuationEvaluation(
            criterionClass: self::class,
            decision: ContinuationDecision::ForbidContinuation,
            reason: sprintf('Continuation evaluation failed: %s', $message),
            context: [
                'exception' => $error::class,
                'message' => $error->getMessage(),
            ],
            stopReason: StopReason::ErrorForbade,
        );

        return new self(
            shouldContinue: false,
            evaluations: [$evaluation],
        );
    }

    public function shouldContinue(): bool {
        return $this->shouldContinue;
    }

    /**
     * Check if continuation is forbidden by a guard.
     *
     * This is more specific than !shouldContinue() - it indicates
     * that a ForbidContinuation evaluation was present, not just
     * that no RequestContinuation was found.
     */
    public function isForbidden(): bool {
        return $this->decision() === ContinuationDecision::ForbidContinuation;
    }

    /**
     * Get the aggregate decision (for backward compatibility).
     */
    public function decision(): ContinuationDecision {
        return EvaluationProcessor::determineDecision($this->evaluations, $this->shouldContinue);
    }

    /**
     * Get the criterion that resolved the decision.
     *
     * @return class-string|'aggregate'
     */
    public function resolvedBy(): string {
        return EvaluationProcessor::findResolver($this->evaluations, $this->shouldContinue);
    }

    /**
     * Get the stop reason.
     */
    public function stopReason(): StopReason {
        return EvaluationProcessor::determineStopReason($this->evaluations, $this->shouldContinue);
    }

    /**
     * @param class-string $criterionClass
     */
    public function getEvaluationFor(string $criterionClass): ?ContinuationEvaluation {
        foreach ($this->evaluations as $eval) {
            if ($eval->criterionClass === $criterionClass) {
                return $eval;
            }
        }
        return null;
    }

    /**
     * @return class-string|null
     */
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
            'decision' => $this->decision()->value,
            'shouldContinue' => $this->shouldContinue,
            'resolvedBy' => $this->resolvedBy(),
            'stopReason' => $this->stopReason()->value,
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

    public static function fromArray(array $data): self {
        $evaluations = array_map(
            static fn(array $eval): ContinuationEvaluation => ContinuationEvaluation::fromArray($eval),
            $data['evaluations'] ?? [],
        );

        return new self(
            shouldContinue: $data['shouldContinue'] ?? false,
            evaluations: $evaluations,
        );
    }
}

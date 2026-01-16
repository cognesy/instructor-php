<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

final readonly class ContinuationEvaluation
{
    public function __construct(
        public string $criterionClass,
        public ContinuationDecision $decision,
        public string $reason,
        public array $context = [],
    ) {}

    public static function fromDecision(
        string $criterionClass,
        ContinuationDecision $decision,
    ): self {
        return new self(
            criterionClass: $criterionClass,
            decision: $decision,
            reason: self::defaultReason($criterionClass, $decision),
        );
    }

    private static function defaultReason(string $class, ContinuationDecision $decision): string {
        $shortName = substr(strrchr($class, '\\') ?: $class, 1);
        return match ($decision) {
            ContinuationDecision::ForbidContinuation => "{$shortName} forbade continuation",
            ContinuationDecision::RequestContinuation => "{$shortName} requested continuation",
            ContinuationDecision::AllowContinuation => "{$shortName} permits continuation",
            ContinuationDecision::AllowStop => "{$shortName} allows stop",
        };
    }
}

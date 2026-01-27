<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Continuation\Data;

use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;

final readonly class ContinuationEvaluation
{
    /**
     * @param class-string $criterionClass
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $criterionClass,
        public ContinuationDecision $decision,
        public string $reason,
        public array $context = [],
        public ?StopReason $stopReason = null,
    ) {}

    /**
     * @param class-string $criterionClass
     */
    public static function fromDecision(
        string $criterionClass,
        ContinuationDecision $decision,
        ?StopReason $stopReason = null,
    ): self {
        return new self(
            criterionClass: $criterionClass,
            decision: $decision,
            reason: self::defaultReason($criterionClass, $decision),
            stopReason: $stopReason,
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

    public static function fromArray(array $data): self {
        return new self(
            criterionClass: $data['criterion'] ?? 'unknown',
            decision: ContinuationDecision::from($data['decision'] ?? 'allow_continuation'),
            reason: $data['reason'] ?? '',
            context: $data['context'] ?? [],
            stopReason: isset($data['stopReason']) ? StopReason::from($data['stopReason']) : null,
        );
    }
}

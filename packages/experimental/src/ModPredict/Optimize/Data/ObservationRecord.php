<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize\Data;

final class ObservationRecord
{
    public function __construct(
        public readonly string $signatureId,
        public readonly string $modelId,
        public readonly ?string $presetVersion,
        public readonly mixed $input,
        public readonly mixed $output,
        public readonly string $acceptance,
        public readonly int $latencyMs,
        public readonly ?array $tokenUsage,
        public readonly ?array $error,
        public readonly array $context,
        public readonly \DateTimeImmutable $observedAt,
    ) {}
}
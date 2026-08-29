<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

/** One portable effort's provider representation and effective meaning. */
final readonly class ReasoningEffortMapping
{
    public function __construct(
        public ReasoningEffort $requested,
        public string $providerValue,
        public ReasoningEffort $effective,
        public ReasoningMappingQuality $quality = ReasoningMappingQuality::Exact,
        public bool $documented = true,
    ) {}
}

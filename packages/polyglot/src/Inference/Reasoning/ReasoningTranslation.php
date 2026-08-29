<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

/** Validated provider translation with explicit semantic quality. */
final readonly class ReasoningTranslation
{
    public function __construct(
        public ReasoningOptions $options,
        public ReasoningMappingQuality $quality,
        public ReasoningSelection $requested,
        public ?ReasoningSelection $effective = null,
    ) {}

    public static function omitted(ReasoningSelection $requested): self
    {
        return new self(
            options: ReasoningOptions::empty(),
            quality: ReasoningMappingQuality::Exact,
            requested: $requested,
            effective: $requested,
        );
    }
}

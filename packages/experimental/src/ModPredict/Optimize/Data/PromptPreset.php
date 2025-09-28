<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize\Data;

final class PromptPreset
{
    public function __construct(
        public readonly string $signatureId,
        public readonly string $modelId,
        public readonly string $version,
        public readonly string $instructions,
        public readonly array $fewShots = [],
        public readonly array $inferenceConfig = [],
        public readonly array $metadata = [],
        public readonly string $status = 'draft', // draft|active|canary|retired
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $activatedAt = null,
    ) {}
}


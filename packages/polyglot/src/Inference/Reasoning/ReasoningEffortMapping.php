<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

use InvalidArgumentException;

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

    public static function fromArray(array $data): self {
        $requested = self::requiredString($data, 'requested');
        $provider = self::requiredString($data, 'provider');
        $effective = $data['effective'] ?? $requested;
        $quality = $data['quality'] ?? ReasoningMappingQuality::Exact->value;
        $documented = $data['documented'] ?? true;

        if (!is_string($effective) || !is_string($quality) || !is_bool($documented)) {
            throw new InvalidArgumentException('Invalid reasoning effort mapping.');
        }

        return new self(
            requested: ReasoningEffort::parse($requested),
            providerValue: $provider,
            effective: ReasoningEffort::parse($effective),
            quality: ReasoningMappingQuality::tryFrom($quality)
                ?? throw new InvalidArgumentException("Invalid reasoning mapping quality: {$quality}"),
            documented: $documented,
        );
    }

    /** @return array<string, string|bool> */
    public function toArray(): array {
        return [
            'requested' => $this->requested->value,
            'provider' => $this->providerValue,
            ...match ($this->effective) {
                $this->requested => [],
                default => ['effective' => $this->effective->value],
            },
            ...match ($this->quality) {
                ReasoningMappingQuality::Exact => [],
                default => ['quality' => $this->quality->value],
            },
            ...match ($this->documented) {
                true => [],
                false => ['documented' => false],
            },
        ];
    }

    private static function requiredString(array $data, string $key): string {
        $value = $data[$key] ?? null;

        return match (true) {
            is_string($value) && $value !== '' => $value,
            default => throw new InvalidArgumentException("Reasoning effort mapping requires {$key}."),
        };
    }
}

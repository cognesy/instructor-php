<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

use InvalidArgumentException;

/** Collection of model-specific named effort mappings. */
final readonly class ReasoningEffortMappings
{
    /** @var list<ReasoningEffortMapping> */
    private array $mappings;

    public function __construct(ReasoningEffortMapping ...$mappings) {
        $this->mappings = array_values($mappings);
    }

    public static function none(): self {
        return new self();
    }

    public static function exact(ReasoningEffort ...$efforts): self {
        return new self(...array_map(
            static fn (ReasoningEffort $effort): ReasoningEffortMapping => new ReasoningEffortMapping(
                requested: $effort,
                providerValue: $effort->value,
                effective: $effort,
            ),
            $efforts,
        ));
    }

    public static function fromArray(array $data): self {
        return new self(...array_map(
            static fn (mixed $mapping): ReasoningEffortMapping => match (true) {
                is_array($mapping) => ReasoningEffortMapping::fromArray($mapping),
                default => throw new InvalidArgumentException('Reasoning effort mappings must be objects.'),
            },
            array_values($data),
        ));
    }

    public function find(ReasoningEffort $effort): ?ReasoningEffortMapping {
        foreach ($this->mappings as $mapping) {
            if ($mapping->requested === $effort) {
                return $mapping;
            }
        }

        return null;
    }

    /** @return list<ReasoningEffortMapping> */
    public function all(): array {
        return $this->mappings;
    }

    /** @return list<array<string, string|bool>> */
    public function toArray(): array {
        return array_map(
            static fn (ReasoningEffortMapping $mapping): array => $mapping->toArray(),
            $this->mappings,
        );
    }
}

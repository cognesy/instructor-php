<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

use InvalidArgumentException;

/** Collection of supported reasoning selection kinds. */
final readonly class ReasoningSelectionKinds
{
    /** @var list<ReasoningSelectionKind> */
    private array $kinds;

    public function __construct(ReasoningSelectionKind ...$kinds) {
        $this->kinds = array_values(array_unique($kinds, SORT_REGULAR));
    }

    public static function none(): self {
        return new self();
    }

    public static function fromArray(array $data): self {
        if (!array_is_list($data)) {
            throw new InvalidArgumentException('Reasoning selections must be a list.');
        }
        $kinds = array_map(
            static fn (mixed $kind): ReasoningSelectionKind => match (true) {
                is_string($kind) => ReasoningSelectionKind::tryFrom($kind)
                    ?? throw new InvalidArgumentException("Invalid reasoning selection kind: {$kind}"),
                default => throw new InvalidArgumentException('Reasoning selections must be strings.'),
            },
            $data,
        );

        return new self(...$kinds);
    }

    public function contains(ReasoningSelectionKind $kind): bool {
        return in_array($kind, $this->kinds, true);
    }

    /** @return list<ReasoningSelectionKind> */
    public function all(): array {
        return $this->kinds;
    }

    /** @return list<string> */
    public function toArray(): array {
        return array_map(
            static fn (ReasoningSelectionKind $kind): string => $kind->value,
            $this->kinds,
        );
    }
}

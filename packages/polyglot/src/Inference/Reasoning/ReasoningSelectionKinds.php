<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

/** Collection of supported reasoning selection kinds. */
final readonly class ReasoningSelectionKinds
{
    /** @var list<ReasoningSelectionKind> */
    private array $kinds;

    public function __construct(ReasoningSelectionKind ...$kinds)
    {
        $this->kinds = array_values(array_unique($kinds, SORT_REGULAR));
    }

    public static function none(): self
    {
        return new self;
    }

    public function contains(ReasoningSelectionKind $kind): bool
    {
        return in_array($kind, $this->kinds, true);
    }

    /** @return list<ReasoningSelectionKind> */
    public function all(): array
    {
        return $this->kinds;
    }
}

<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

final readonly class CanonicalLineage
{
    /** @param list<CanonicalHash> $compactedFrom */
    public function __construct(
        private CanonicalHash $root,
        private ?CanonicalHash $parent = null,
        private array $compactedFrom = [],
    ) {
        $seen = [];
        foreach ($compactedFrom as $hash) {
            if (! $hash instanceof CanonicalHash) {
                throw new CanonicalValidationException('Canonical compaction provenance must contain hashes.');
            }
            $value = $hash->toString();
            if (isset($seen[$value])) {
                throw new CanonicalValidationException('Canonical compaction provenance must not repeat a hash.');
            }
            if ($value === $this->root->toString() || $value === $this->parent?->toString()) {
                throw new CanonicalValidationException('Canonical compaction provenance cannot repeat root or parent.');
            }
            $seen[$value] = true;
        }
        if ($this->parent?->equals($this->root)) {
            throw new CanonicalValidationException('Canonical turn parent cannot be its conversation root.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        CanonicalInput::assertKeys($data, ['root'], ['parent', 'compactedFrom']);
        $compactedFrom = array_map(
            static fn (mixed $hash): CanonicalHash => CanonicalInput::hash($hash, 'compaction provenance'),
            CanonicalInput::list($data['compactedFrom'] ?? [], 'compaction provenance'),
        );

        return new self(
            CanonicalInput::hash($data['root'], 'conversation root'),
            array_key_exists('parent', $data) ? CanonicalInput::hash($data['parent'], 'turn parent') : null,
            $compactedFrom,
        );
    }

    public function root(): CanonicalHash
    {
        return $this->root;
    }

    public function parent(): ?CanonicalHash
    {
        return $this->parent;
    }

    /** @return list<CanonicalHash> */
    public function compactedFrom(): array
    {
        return $this->compactedFrom;
    }

    /** @return array<string, string|list<string>> */
    public function toCanonicalArray(): array
    {
        $data = ['root' => $this->root->toString()];
        if ($this->parent !== null) {
            $data['parent'] = $this->parent->toString();
        }
        if ($this->compactedFrom !== []) {
            $data['compactedFrom'] = array_map(
                static fn (CanonicalHash $hash): string => $hash->toString(),
                $this->compactedFrom,
            );
        }

        return $data;
    }
}

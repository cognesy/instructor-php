<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena\Record;

use Cognesy\Tell\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Workspace\Arena\RecordException;

final readonly class Lineage
{
    /** @param list<ObjectHash> $compactedFrom */
    public function __construct(
        private ObjectHash $root,
        private ?ObjectHash $parent = null,
        private array $compactedFrom = [],
    ) {
        $seen = [];
        foreach ($compactedFrom as $hash) {
            if (!$hash instanceof ObjectHash) {
                throw new RecordException('Arena compaction provenance must contain object hashes.');
            }
            $value = $hash->toString();
            if (isset($seen[$value])) {
                throw new RecordException('Arena compaction provenance must not repeat a hash.');
            }
            if ($value === $this->root->toString() || $value === $this->parent?->toString()) {
                throw new RecordException('Arena compaction provenance cannot repeat root or parent.');
            }
            $seen[$value] = true;
        }
        if ($this->parent?->equals($this->root)) {
            throw new RecordException('Arena turn parent cannot be its conversation root.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        Input::assertKeys($data, ['root'], ['parent', 'compactedFrom']);
        $compactedFrom = array_map(
            static fn (mixed $hash): ObjectHash => Input::hash($hash, 'compaction provenance'),
            Input::list($data['compactedFrom'] ?? [], 'compaction provenance'),
        );

        return new self(
            Input::hash($data['root'], 'conversation root'),
            array_key_exists('parent', $data) ? Input::hash($data['parent'], 'turn parent') : null,
            $compactedFrom,
        );
    }

    public function root(): ObjectHash {
        return $this->root;
    }

    public function parent(): ?ObjectHash {
        return $this->parent;
    }

    /** @return list<ObjectHash> */
    public function compactedFrom(): array {
        return $this->compactedFrom;
    }

    /** @return array<string, string|list<string>> */
    public function toArray(): array {
        $data = ['root' => $this->root->toString()];
        if ($this->parent !== null) {
            $data['parent'] = $this->parent->toString();
        }
        if ($this->compactedFrom !== []) {
            $data['compactedFrom'] = array_map(
                static fn (ObjectHash $hash): string => $hash->toString(),
                $this->compactedFrom,
            );
        }

        return $data;
    }
}

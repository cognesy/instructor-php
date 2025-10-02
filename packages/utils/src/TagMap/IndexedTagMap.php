<?php declare(strict_types=1);

namespace Cognesy\Utils\TagMap;

use Cognesy\Utils\TagMap\Contracts\TagInterface;
use Cognesy\Utils\TagMap\Contracts\TagMapInterface;

/**
 * Minimal tag map implementation with sequential IDs and efficient storage.
 *
 * Uses dual indexing:
 * - tagsById: id => tag (O(1) lookup)
 * - insertionOrder: [id1, id2, id3] (preserves addition order)
 */
final class IndexedTagMap implements TagMapInterface
{
    private array $tagsById;           // string => TagInterface
    private array $insertionOrder;     // array<string>
    private static int $nextId = 1;

    private function __construct(
        array $tagsById = [],
        array $insertionOrder = []
    ) {
        $this->tagsById = $tagsById;
        $this->insertionOrder = $insertionOrder;
    }

    // CONSTRUCTORS

    /**
     * @param TagInterface[] $tags
     */
    #[\Override]
    public static function create(array $tags): self {
        return self::empty()->add(...$tags);
    }

    #[\Override]
    public static function empty(): self {
        return new self();
    }

    // PUBLIC API

    public function count(): int {
        return count($this->insertionOrder);
    }

    /**
     * @return TagInterface[]
     */
    #[\Override]
    public function getAllInOrder(): array {
        return array_map(
            fn(string $id): TagInterface => $this->tagsById[$id],
            $this->insertionOrder,
        );
    }

    #[\Override]
    public function has(string $tagClass): bool {
        foreach ($this->tagsById as $tag) {
            if ($tag instanceof $tagClass) {
                return true;
            }
        }
        return false;
    }

    #[\Override]
    public function isEmpty(): bool {
        return empty($this->insertionOrder);
    }

    #[\Override]
    public function merge(TagMapInterface $other): TagMapInterface {
        if ($other->isEmpty()) {
            return $this;
        }
        if ($this->isEmpty()) {
            return $other;
        }
        return $this->add(...$other->getAllInOrder());
    }

    #[\Override]
    public function mergeInto(TagMapInterface $target): TagMapInterface {
        if ($this->isEmpty()) {
            return $target;
        }
        if ($target->isEmpty()) {
            return $this;
        }
        return $target->add(...$this->getAllInOrder());
    }

    #[\Override]
    public function newInstance(array $tags): TagMapInterface {
        return self::empty()->add(...$tags);
    }

    #[\Override]
    public function query(): TagQuery {
        return new TagQuery($this);
    }

    #[\Override]
    public function add(TagInterface ...$tags): self {
        if (empty($tags)) {
            return $this;
        }
        $newTagsById = $this->tagsById;
        $newInsertionOrder = $this->insertionOrder;
        foreach ($tags as $tag) {
            $id = $this->generateId();
            $newTagsById[$id] = $tag;
            $newInsertionOrder[] = $id;
        }
        return new self($newTagsById, $newInsertionOrder);
    }

    // INTERNAL

    private function generateId(): string {
        return (string) self::$nextId++;
    }

    #[\Override]
    public function replace(TagInterface ...$tags): TagMapInterface {
        $this->tagsById = [];
        $this->insertionOrder = [];
        return $this->add(...$tags);
    }
}
<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tag\Internal;

use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Contracts\TagMapInterface;
use Cognesy\Pipeline\Query\TagQuery;

/**
 * Minimal tag map implementation with sequential IDs and efficient storage.
 *
 * Uses dual indexing:
 * - tagsById: id => tag (O(1) lookup)
 * - insertionOrder: [id1, id2, id3] (preserves addition order)
 */
final class IndexedTagMap implements TagMapInterface
{
    private array $tagsById = [];           // string => TagInterface
    private array $insertionOrder = [];     // array<string>
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
     * @param array<TagInterface> $tags
     */
    public static function create(array $tags): self {
        return self::empty()->with(...$tags);
    }

    public static function empty(): self {
        return new self();
    }

    // PUBLIC API

    public function count(): int {
        return count($this->insertionOrder);
    }

    /**
     * @return array<TagInterface>
     */
    public function getAllInOrder(): array {
        return array_map(
            fn(string $id): TagInterface => $this->tagsById[$id],
            $this->insertionOrder,
        );
    }

    public function has(string $tagClass): bool {
        foreach ($this->tagsById as $tag) {
            if ($tag instanceof $tagClass) {
                return true;
            }
        }
        return false;
    }

    public function isEmpty(): bool {
        return empty($this->insertionOrder);
    }

    public function merge(TagMapInterface $added): self {
        if ($added->isEmpty()) {
            return $this;
        }
        return $this->with(...$added->getAllInOrder());
    }

    public function newInstance(array $tags): TagMapInterface {
        return self::empty()->with(...$tags);
    }

    public function query(): TagQuery {
        return new TagQuery($this);
    }

    public function with(TagInterface ...$tags): self {
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
}
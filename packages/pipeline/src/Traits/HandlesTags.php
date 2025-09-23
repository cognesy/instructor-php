<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Traits;

use Cognesy\Utils\TagMap\Contracts\TagInterface;
use Cognesy\Utils\TagMap\Contracts\TagMapInterface;
use Cognesy\Utils\TagMap\IndexedTagMap;
use Cognesy\Utils\TagMap\TagQuery;

trait HandlesTags
{
    protected readonly TagMapInterface $tags;

    public function tags(): TagQuery {
        return $this->tagMap()->query();
    }

    public function addTags(TagInterface ...$tags): static {
        return new self($this->result, $this->tags->add(...$tags));
    }

    public function replaceTags(TagInterface ...$tags): static {
        return new self($this->result, $this->tags->replace(...$tags));
    }

    public function tagMap(): TagMapInterface {
        return $this->tags;
    }

    /**
     * Get all tags, optionally filtered by class.
     *
     * @param class-string|null $tagClass Optional class filter
     * @return TagInterface[]
     */
    public function allTags(?string $tagClass = null): array {
        return $this->tags->query()->only($tagClass)->all();
    }

    /**
     * @param class-string $tagClass
     */
    public function hasTag(string $tagClass): bool {
        return $this->tags->has($tagClass);
    }

    // INTERNAL //////////////////////////////////////////////////

    protected static function defaultTagMap(?array $tags = []): TagMapInterface {
        return match(true) {
            $tags === null => IndexedTagMap::empty(),
            default => IndexedTagMap::create($tags),
        };
    }
}
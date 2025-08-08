<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tag;

use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Contracts\TagMapInterface;

class TagTransform {
    private TagMapInterface $tagMap;
    /** @var TagInterface[] */
    private array $tags;

    public function __construct(TagMapInterface $tagMap) {
        $this->tagMap = $tagMap;
        $this->tags = $tagMap->getAllInOrder();
    }

    public function get() : TagMapInterface {
        return $this->tagMap;
    }

    public function map(string $tagClass, callable $callback): TagMapInterface {
        if (!$this->tagMap->has($tagClass)) {
            return $this->tagMap;
        }
        $mappedTags = [];
        foreach ($this->tags as $tag) {
            $mappedTags[] = match(true) {
                $tag instanceof $tagClass => $callback($tag),
                default => $tag
            };
        }
        return $this->tagMap->newInstance($mappedTags);
    }
}
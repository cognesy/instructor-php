<?php

namespace Cognesy\Instructor\Reflection\Tag;

use Cognesy\Instructor\Reflection\PhpDoc\DocstringUtils;
use Jasny\PhpdocParser\PhpdocParser;
use Jasny\PhpdocParser\Tag\MultiTag;
use Jasny\PhpdocParser\Tag\PhpDocumentor\VarTag;
use Jasny\PhpdocParser\TagSet;

class TagCollection
{
    private array $tags;

    public function __construct(array $tags = []) {
        $this->tags = $tags;
    }

    /**
     * @return TypeDefTag[]
     */
    public function all(): array
    {
        return $this->tags;
    }

    public function has(string $tagName, string $fieldName): bool
    {
        foreach($this->tags as $tag) {
            if ($tag->tag === $tagName && $tag->name === $fieldName) {
                return true;
            }
        }
        return false;
    }

    public function get(string $tagName, string $fieldName): ?TypeDefTag
    {
        foreach($this->tags as $tag) {
            if ($tag->tag === $tagName && $tag->name === $fieldName) {
                return $tag;
            }
        }
        return null;
    }

    /**
     * @return TypeDefTag[]
     */
    public function getEach(string $name): array
    {
        $list = [];
        foreach($this->tags as $tag) {
            if ($tag->name === $name) {
                $list[] = $tag;
            }
        }
        return $list;
    }

    static public function extract(string $docComment, array $tagTypes, string $varName) : self
    {
        $docComment = trim(DocstringUtils::removeMarkers($docComment));
        if (empty($docComment)) {
            return new TagCollection();
        }

        # extract from docstring
        $tags = [];
        foreach($tagTypes as $tagType) {
            $tags[] = new MultiTag($tagType, new VarTag($tagType));
        }
        $tagSet = new TagSet($tags);
        $parsed = (new PhpDocParser($tagSet))->parse($docComment);

        // move to TypeDefTag object array
        $tags = [];
        foreach($parsed as $tagGroupName => $items) {
            foreach($items as $tagData) {
                // if tag name is specified and does not match context var, skip
                if (!empty($tagData['name']) && ($tagData['name'] != $varName)) {
                    continue;
                }
                $tags[] = new TypeDefTag(
                    tag: $tagGroupName,
                    name: $tagData['name'] ?? '',
                    type: $tagData['type'] ?? '',
                    description: $tagData['description'] ?? '',
                );
            }
        }

        return new TagCollection($tags);
    }
}
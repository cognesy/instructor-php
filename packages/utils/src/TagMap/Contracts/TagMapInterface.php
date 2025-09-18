<?php declare(strict_types=1);

namespace Cognesy\Utils\TagMap\Contracts;

use Cognesy\Utils\TagMap\TagQuery;

/**
 * Universal interface for tag storage implementations.
 * Provides minimal surface area while enabling all required functionality.
 */
interface TagMapInterface
{
    /** @param array<TagInterface> $tags */
    public static function create(array $tags): self;
    public static function empty(): self;

    /** @return array<TagInterface> */
    public function getAllInOrder(): array;
    public function has(string $tagClass): bool;
    public function isEmpty(): bool;

    public function merge(self $other): self;
    public function mergeInto(self $target): self;

    /** @param array<TagInterface> $tags */
    public function newInstance(array $tags): self;
    public function add(TagInterface ...$tags): self;
    public function replace(TagInterface ...$tags): self;

    public function query(): TagQuery;
}
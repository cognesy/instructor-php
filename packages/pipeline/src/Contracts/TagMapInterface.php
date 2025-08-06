<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

use Cognesy\Pipeline\Query\TagQuery;

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

    /** @param array<TagInterface> $tags */
    public function newInstance(array $tags): self;
    public function query(): TagQuery;
    public function with(TagInterface ...$tags): self;
}
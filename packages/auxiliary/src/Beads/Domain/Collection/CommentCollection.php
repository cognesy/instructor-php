<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Collection;

use Cognesy\Auxiliary\Beads\Domain\Model\Comment;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Comment Collection
 *
 * Rich domain collection for Comment entities with filtering and sorting
 *
 * @implements IteratorAggregate<int, Comment>
 */
final class CommentCollection implements Countable, IteratorAggregate
{
    /**
     * @param  array<Comment>  $items
     */
    public function __construct(
        private array $items = [],
    ) {}

    /**
     * Create from array of comments
     *
     * @param  array<Comment>  $comments
     */
    public static function from(array $comments): self
    {
        return new self($comments);
    }

    /**
     * Create empty collection
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Add comment to collection
     */
    public function add(Comment $comment): self
    {
        $items = $this->items;
        $items[] = $comment;

        return new self($items);
    }

    /**
     * Filter comments by predicate
     *
     * @param  callable(Comment): bool  $predicate
     */
    public function filter(callable $predicate): self
    {
        return new self(array_filter($this->items, $predicate));
    }

    /**
     * Map comments to array
     *
     * @template T
     *
     * @param  callable(Comment): T  $mapper
     * @return array<T>
     */
    public function map(callable $mapper): array
    {
        return array_map($mapper, $this->items);
    }

    /**
     * Filter comments by author
     */
    public function byAuthor(Agent $author): self
    {
        return $this->filter(fn (Comment $comment) => $comment->isAuthoredBy($author));
    }

    /**
     * Filter comments with mentions
     */
    public function withMentions(): self
    {
        return $this->filter(fn (Comment $comment) => $comment->hasMentions());
    }

    /**
     * Filter comments mentioning specific agent
     */
    public function mentioning(Agent $agent): self
    {
        return $this->filter(fn (Comment $comment) => $comment->mentions($agent));
    }

    /**
     * Sort comments by creation date (newest first)
     */
    public function sortByNewest(): self
    {
        $items = $this->items;
        usort($items, fn (Comment $a, Comment $b) => $b->createdAt <=> $a->createdAt
        );

        return new self($items);
    }

    /**
     * Sort comments by creation date (oldest first)
     */
    public function sortByOldest(): self
    {
        $items = $this->items;
        usort($items, fn (Comment $a, Comment $b) => $a->createdAt <=> $b->createdAt
        );

        return new self($items);
    }

    /**
     * Get first comment or null
     */
    public function first(): ?Comment
    {
        return $this->items[0] ?? null;
    }

    /**
     * Get last comment or null
     */
    public function last(): ?Comment
    {
        $items = $this->items;

        return empty($items) ? null : end($items);
    }

    /**
     * Check if collection is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if collection is not empty
     */
    public function isNotEmpty(): bool
    {
        return ! empty($this->items);
    }

    /**
     * Get collection as array
     *
     * @return array<Comment>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Count comments in collection
     */
    #[\Override]
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get iterator
     *
     * @return Traversable<int, Comment>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}

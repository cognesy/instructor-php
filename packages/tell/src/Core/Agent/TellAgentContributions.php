<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Agent;

use ArrayIterator;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;
use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, CanContributeTellAgent> */
final readonly class TellAgentContributions implements Countable, IteratorAggregate
{
    /** @var list<CanContributeTellAgent> */
    private array $items;

    public function __construct(CanContributeTellAgent ...$items) {
        $this->items = array_values($items);
    }

    public static function empty(): self {
        return new self();
    }

    public function with(CanContributeTellAgent ...$items): self {
        return new self(...$this->items, ...$items);
    }

    /** @return list<CanContributeTellAgent> */
    public function all(): array {
        return $this->items;
    }

    #[Override]
    public function count(): int {
        return count($this->items);
    }

    /** @return Traversable<int, CanContributeTellAgent> */
    #[Override]
    public function getIterator(): Traversable {
        return new ArrayIterator($this->items);
    }
}

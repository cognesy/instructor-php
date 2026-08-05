<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, object> */
final readonly class EvalEvents implements Countable, IteratorAggregate
{
    /** @var list<object> */
    private array $events;

    public function __construct(object ...$events) {
        $this->events = $events;
    }

    public static function none(): self {
        return new self();
    }

    public function with(object ...$events): self {
        return new self(...[...$this->events, ...$events]);
    }

    /** @return list<object> */
    public function all(): array {
        return $this->events;
    }

    #[Override]
    public function count(): int {
        return count($this->events);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->events;
    }
}

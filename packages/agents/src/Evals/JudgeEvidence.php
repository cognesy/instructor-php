<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/**
 * Immutable ordered collection of concise evidence strings backing a `JudgeScore`.
 *
 * Evidence is developer-visible support for the score - observed tool activity,
 * source identifiers, test results, or trace facts. It is not hidden model
 * reasoning and must never be described as such.
 *
 * @implements IteratorAggregate<int, string>
 */
final readonly class JudgeEvidence implements Countable, IteratorAggregate
{
    /** @var list<string> */
    private array $items;

    public function __construct(string ...$items) {
        $this->items = array_values($items);
    }

    public static function none(): self {
        return new self();
    }

    public static function of(string ...$items): self {
        return new self(...$items);
    }

    public function with(string ...$items): self {
        return new self(...[...$this->items, ...$items]);
    }

    /** @return list<string> */
    public function all(): array {
        return $this->items;
    }

    #[Override]
    public function count(): int {
        return count($this->items);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->items;
    }

    /** @return list<string> */
    public function toArray(): array {
        return $this->items;
    }

    /** @param list<mixed> $data */
    public static function fromArray(array $data): self {
        $items = [];
        foreach ($data as $entry) {
            if (is_string($entry)) {
                $items[] = $entry;
            }
        }
        return new self(...$items);
    }
}

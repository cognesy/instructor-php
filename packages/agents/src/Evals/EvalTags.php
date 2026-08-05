<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, string> */
final readonly class EvalTags implements Countable, IteratorAggregate
{
    /** @var list<string> */
    private array $tags;

    public function __construct(string ...$tags) {
        $normalized = array_values(array_unique(array_filter(
            array_map(static fn (string $tag): string => trim($tag), $tags),
            static fn (string $tag): bool => $tag !== '',
        )));
        sort($normalized);
        $this->tags = $normalized;
    }

    public static function of(string ...$tags): self {
        return new self(...$tags);
    }

    public static function none(): self {
        return new self();
    }

    public function has(string $tag): bool {
        return in_array($tag, $this->tags, true);
    }

    /** @return list<string> */
    public function all(): array {
        return $this->tags;
    }

    #[Override]
    public function count(): int {
        return count($this->tags);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->tags;
    }
}

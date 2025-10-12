<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Array;

use Cognesy\Stream\Contracts\Stream;
use Iterator;

/**
 * @implements Stream<int, mixed>
 */
final readonly class ArrayStream implements Stream
{
    /**
     * @param array<int|string, mixed> $items
     */
    private function __construct(private array $items) {}

    /**
     * @param array<int|string, mixed> $items
     */
    public static function from(array $items): self {
        return new self($items);
    }

    #[\Override]
    public function getIterator(): Iterator {
        foreach ($this->items as $item) {
            yield $item;
        }
    }
}

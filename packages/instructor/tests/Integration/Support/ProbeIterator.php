<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Support;

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Iterator;

/**
 * Iterator over a fixed array of PartialInferenceResponse that
 * tracks how many times next() has been called to assert no read-ahead.
 */
class ProbeIterator implements Iterator
{
    /** @var PartialInferenceResponse[] */
    private array $items;
    private int $index = 0;
    public int $advanced = 0;

    /**
     * @param PartialInferenceResponse[] $items
     */
    public function __construct(array $items)
    {
        $this->items = array_values($items);
    }

    public function current(): mixed { return $this->items[$this->index] ?? null; }
    public function key(): mixed { return $this->index; }
    public function next(): void { $this->index++; $this->advanced++; }
    public function rewind(): void { $this->index = 0; $this->advanced = 0; }
    public function valid(): bool { return $this->index < count($this->items); }
}


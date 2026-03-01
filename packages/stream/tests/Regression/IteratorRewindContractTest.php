<?php declare(strict_types=1);

use Cognesy\Stream\Support\Tee;
use Cognesy\Stream\Transform\Map\Transducers\Map;
use Cognesy\Stream\Transformation;

final class RequiresRewindIterator implements Iterator
{
    /** @var list<mixed> */
    private array $items;
    private int $index = 0;
    private bool $rewound = false;

    /** @param list<mixed> $items */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    #[\Override]
    public function current(): mixed
    {
        return $this->items[$this->index];
    }

    #[\Override]
    public function next(): void
    {
        $this->index++;
    }

    #[\Override]
    public function key(): int
    {
        return $this->index;
    }

    #[\Override]
    public function valid(): bool
    {
        if (!$this->rewound) {
            return false;
        }

        return isset($this->items[$this->index]);
    }

    #[\Override]
    public function rewind(): void
    {
        $this->rewound = true;
        $this->index = 0;
    }
}

it('processes iterators that become valid only after rewind in transformation execution', function () {
    $input = new RequiresRewindIterator([1, 2, 3]);

    $result = (new Transformation())
        ->through(new Map(fn(int $x): int => $x * 2))
        ->withInput($input)
        ->execute();

    expect($result)->toBe([2, 4, 6]);
});

it('supports rewind-required iterators in Tee::split', function () {
    $input = new RequiresRewindIterator([1, 2, 3]);

    [$first, $second] = Tee::split($input, 2);

    expect(iterator_to_array($first, false))->toBe([1, 2, 3]);
    expect(iterator_to_array($second, false))->toBe([1, 2, 3]);
});

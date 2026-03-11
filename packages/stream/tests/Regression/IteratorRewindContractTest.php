<?php declare(strict_types=1);

use Cognesy\Stream\Transform\Map\Transducers\Map;
use Cognesy\Stream\Transformation;

final class RewindTrackingIterator implements Iterator
{
    /** @var list<mixed> */
    private array $items;
    private int $index = 0;
    private int $rewindCount = 0;

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
        return isset($this->items[$this->index]);
    }

    #[\Override]
    public function rewind(): void
    {
        $this->rewindCount++;
        $this->index = 0;
    }

    public function rewindCount(): int
    {
        return $this->rewindCount;
    }
}

it('processes iterators without implicitly rewinding them in transformation execution', function () {
    $input = new RewindTrackingIterator([1, 2, 3]);

    $result = (new Transformation())
        ->through(new Map(fn(int $x): int => $x * 2))
        ->withInput($input)
        ->execute();

    expect($result)->toBe([2, 4, 6]);
    expect($input->rewindCount())->toBe(0);
});

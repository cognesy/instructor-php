<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\IteratorUtils;

/**
 * Interleaves values from multiple iterables into the main stream.
 */
final class InterleaveReducer implements Reducer
{
    /** @var array<int, \Iterator> */
    private array $iterators = [];
    private int $totalIterators;

    public function __construct(
        private Reducer $reducer,
        iterable ...$iterables,
    ) {
        foreach ($iterables as $iterable) {
            $iterator = IteratorUtils::toIterator($iterable);
            if ($iterator->valid()) {
                $this->iterators[] = $iterator;
            }
        }
        $this->totalIterators = count($this->iterators);
    }

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $accumulator = $this->reducer->step($accumulator, $reducible);
        if ($this->totalIterators === 0) {
            return $accumulator;
        }
        foreach ($this->iterators as $index => $iterator) {
            if ($iterator->valid()) {
                $accumulator = $this->reducer->step($accumulator, $iterator->current());
                $iterator->next();
                if (!$iterator->valid()) {
                    unset($this->iterators[$index]);
                }
            }
        }
        $this->iterators = array_values($this->iterators);
        $this->totalIterators = count($this->iterators);
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}

<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;

final class ZipReducer implements Reducer
{
    private array $iterators = [];

    public function __construct(
        private Reducer $reducer,
        iterable ...$iterables
    ) {
        foreach ($iterables as $iterable) {
            $this->iterators[] = \Cognesy\Stream\Support\IteratorUtils::toIterator($iterable);
        }
    }

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        // Check if any iterator is exhausted
        foreach ($this->iterators as $iterator) {
            if (!$iterator->valid()) {
                return new Reduced($accumulator);
            }
        }

        // Build tuple with main value and values from all iterators
        $tuple = [$reducible];
        foreach ($this->iterators as $iterator) {
            $tuple[] = $iterator->current();
            $iterator->next();
        }

        return $this->reducer->step($accumulator, $tuple);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}

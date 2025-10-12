<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Decorators;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Reduced;
use Cognesy\Utils\Transducer\Support\IteratorUtils;

final class ZipWithReducer implements Reducer
{
    /** @var array<int, \Iterator> */
    private array $iterators = [];

    /**
     * @param Closure(mixed...): mixed $combineFn
     */
    public function __construct(
        private Closure $combineFn,
        private Reducer $reducer,
        iterable ...$iterables
    ) {
        foreach ($iterables as $iterable) {
            $this->iterators[] = IteratorUtils::toIterator($iterable);
        }
    }

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        foreach ($this->iterators as $iterator) {
            if (!$iterator->valid()) {
                return new Reduced($accumulator);
            }
        }
        $values = [$reducible];
        foreach ($this->iterators as $iterator) {
            $values[] = $iterator->current();
            $iterator->next();
        }
        $combined = ($this->combineFn)(...$values);
        return $this->reducer->step($accumulator, $combined);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}

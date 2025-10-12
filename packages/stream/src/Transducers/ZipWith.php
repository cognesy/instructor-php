<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\ZipWithReducer;

final readonly class ZipWith implements Transducer
{
    private array $iterables;

    /**
     * @param Closure(mixed...): mixed $combineFn
     */
    public function __construct(
        private Closure $combineFn,
        iterable ...$iterables
    ) {
        $this->iterables = $iterables;
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new ZipWithReducer($this->combineFn, $reducer, ...$this->iterables);
    }
}

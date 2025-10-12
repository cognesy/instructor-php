<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\ZipWithReducer;

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

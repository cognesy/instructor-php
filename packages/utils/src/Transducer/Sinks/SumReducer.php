<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Sinks;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;

final readonly class SumReducer implements Reducer
{
    /** @var Closure(mixed): (int|float)|null */
    private ?Closure $mapFn;

    /**
     * @param Closure(mixed): (int|float)|null $mapFn
     */
    public function __construct(?Closure $mapFn = null) {
        $this->mapFn = $mapFn;
    }

    #[\Override]
    public function init(): mixed {
        return 0;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $value = $this->mapFn !== null
            ? ($this->mapFn)($reducible)
            : $reducible;

        return $accumulator + $value;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
